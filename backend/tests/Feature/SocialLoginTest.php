<?php

namespace Tests\Feature;

use App\Models\SocialAccount;
use App\Models\Sport;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;
use Mockery;
use Tests\TestCase;

class SocialLoginTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        Http::preventStrayRequests();
        config([
            'services.google.client_id' => null,
            'services.google.allowed_client_ids' => null,
            'services.facebook.client_id' => null,
        ]);
    }

    private function fakeProvider(string $provider, array $profile): void
    {
        $user = (new SocialiteUser)->map([
            'id' => $profile['id'] ?? '1001',
            'name' => $profile['name'] ?? 'Ana Rojas',
            'email' => array_key_exists('email', $profile) ? $profile['email'] : 'ana@gmail.com',
            'avatar' => $profile['avatar'] ?? null,
        ]);

        $driver = Mockery::mock();
        $driver->shouldReceive('stateless')->andReturnSelf();
        $driver->shouldReceive('userFromToken')->with('provider-token')->andReturn($user);

        Socialite::shouldReceive('driver')->with($provider)->andReturn($driver);
    }

    public function test_a_new_google_user_signs_up_and_must_complete_the_profile(): void
    {
        $this->fakeProvider('google', ['id' => 'g-1', 'name' => 'Ana Rojas', 'email' => 'Ana@Gmail.com']);

        $response = $this->postJson('/api/auth/google', ['access_token' => 'provider-token'])
            ->assertCreated()
            ->assertJsonPath('is_new_user', true)
            ->assertJsonPath('user.name', 'Ana Rojas')
            ->assertJsonPath('user.email', 'ana@gmail.com')
            ->assertJsonPath('user.profile_completed', false);

        $user = User::query()->where('email', 'ana@gmail.com')->firstOrFail();
        $this->assertNull($user->password);
        $this->assertNotNull($user->email_verified_at);
        $this->assertDatabaseHas('social_accounts', ['user_id' => $user->id, 'provider' => 'google', 'provider_id' => 'g-1']);

        $this->withToken($response->json('token'))
            ->getJson('/api/me')
            ->assertJsonPath('user.profile_completed', false);
    }

    public function test_the_same_provider_account_logs_in_again(): void
    {
        $user = User::factory()->create(['email' => 'ana@gmail.com']);
        SocialAccount::query()->create(['user_id' => $user->id, 'provider' => 'facebook', 'provider_id' => 'fb-9']);
        $this->fakeProvider('facebook', ['id' => 'fb-9', 'email' => null]);

        $this->postJson('/api/auth/facebook', ['access_token' => 'provider-token'])
            ->assertOk()
            ->assertJsonPath('is_new_user', false)
            ->assertJsonPath('user.id', $user->id)
            ->assertJsonPath('user.profile_completed', true);

        $this->assertSame(1, User::query()->count());
    }

    public function test_an_existing_email_account_is_linked_instead_of_duplicated(): void
    {
        $user = User::factory()->create(['email' => 'ana@gmail.com', 'gender' => 'female']);
        $this->fakeProvider('google', ['id' => 'g-2', 'email' => 'ANA@gmail.com']);

        $this->postJson('/api/auth/google', ['access_token' => 'provider-token'])
            ->assertOk()
            ->assertJsonPath('is_new_user', false)
            ->assertJsonPath('user.id', $user->id)
            ->assertJsonPath('user.profile_completed', true);

        $this->assertDatabaseHas('social_accounts', ['user_id' => $user->id, 'provider' => 'google']);
        $this->assertSame(1, User::query()->count());
    }

    public function test_a_facebook_account_without_email_cannot_sign_up(): void
    {
        $this->fakeProvider('facebook', ['id' => 'fb-1', 'email' => null]);

        $this->postJson('/api/auth/facebook', ['access_token' => 'provider-token'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('access_token');

        $this->assertSame(0, User::query()->count());
    }

    public function test_an_invalid_token_is_rejected(): void
    {
        $driver = Mockery::mock();
        $driver->shouldReceive('stateless')->andReturnSelf();
        $driver->shouldReceive('userFromToken')->andThrow(new \RuntimeException('401'));
        Socialite::shouldReceive('driver')->with('google')->andReturn($driver);

        $this->postJson('/api/auth/google', ['access_token' => 'bad'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('access_token');

        $this->postJson('/api/auth/twitter', ['access_token' => 'x'])->assertNotFound();
        $this->postJson('/api/auth/google', [])->assertJsonValidationErrors('access_token');
    }

    public function test_a_google_token_issued_to_another_app_is_rejected(): void
    {
        config(['services.google.client_id' => 'web-client', 'services.google.allowed_client_ids' => 'android-client, ios-client']);
        $this->fakeProvider('google', []);

        Http::fake(['oauth2.googleapis.com/*' => Http::sequence()
            ->push(['azp' => 'someone-elses-app'])
            ->push(['azp' => 'android-client'])]);

        $this->postJson('/api/auth/google', ['access_token' => 'provider-token'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('access_token');

        $this->postJson('/api/auth/google', ['access_token' => 'provider-token'])->assertCreated();
    }

    public function test_a_facebook_token_must_belong_to_this_app(): void
    {
        config(['services.facebook.client_id' => '123', 'services.facebook.client_secret' => 'secret']);
        $this->fakeProvider('facebook', []);

        Http::fake(['graph.facebook.com/debug_token*' => Http::sequence()
            ->push(['data' => ['is_valid' => true, 'app_id' => '999']])
            ->push(['data' => ['is_valid' => true, 'app_id' => '123']])]);

        $this->postJson('/api/auth/facebook', ['access_token' => 'provider-token'])->assertUnprocessable();

        $this->postJson('/api/auth/facebook', ['access_token' => 'provider-token'])->assertCreated();
    }

    public function test_the_provider_photo_becomes_the_avatar(): void
    {
        Http::fake(['photos.example.com/*' => Http::response('img', 200, ['Content-Type' => 'image/jpeg'])]);
        $this->fakeProvider('google', ['avatar' => 'https://photos.example.com/ana.jpg']);

        $this->postJson('/api/auth/google', ['access_token' => 'provider-token'])->assertCreated();

        $path = User::query()->firstOrFail()->avatar_path;
        $this->assertNotNull($path);
        Storage::disk('public')->assertExists($path);
    }

    public function test_the_player_completes_the_profile_after_signing_up(): void
    {
        $futbol = Sport::query()->create(['key' => 'futbol_5', 'name' => 'Fútbol 5']);
        $user = User::factory()->create(['profile_completed_at' => null, 'password' => null]);
        Sanctum::actingAs($user);

        $this->postJson('/api/me/complete-profile', ['name' => 'Ana'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('gender');

        $this->postJson('/api/me/complete-profile', [
            'name' => 'Ana Rojas',
            'nickname' => 'Anita',
            'phone' => '70000000',
            'gender' => 'female',
            'preferred_position' => 'Delantera',
            'birth_date' => '1998-05-10',
            'favorite_sport_ids' => [$futbol->id],
        ])->assertOk()
            ->assertJsonPath('user.profile_completed', true)
            ->assertJsonPath('user.nickname', 'Anita')
            ->assertJsonPath('user.gender', 'female')
            ->assertJsonPath('user.favorite_sports.0.name', 'Fútbol 5');

        $this->assertNotNull($user->fresh()->profile_completed_at);
    }

    public function test_an_account_without_password_cannot_log_in_with_one(): void
    {
        User::factory()->create(['email' => 'ana@gmail.com', 'password' => null]);

        $this->postJson('/api/login', ['email' => 'ana@gmail.com', 'password' => ''])
            ->assertUnprocessable();
        $this->postJson('/api/login', ['email' => 'ana@gmail.com', 'password' => 'anything'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('email');
    }

    public function test_the_form_registration_is_already_complete(): void
    {
        $this->postJson('/api/register', [
            'name' => 'Beto',
            'email' => 'beto@test.com',
            'gender' => 'male',
            'password' => 'secret123',
            'password_confirmation' => 'secret123',
        ])->assertCreated()->assertJsonPath('user.profile_completed', true);
    }
}
