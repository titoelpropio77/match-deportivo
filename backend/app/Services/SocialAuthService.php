<?php

namespace App\Services;

use App\Models\SocialAccount;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Laravel\Socialite\Contracts\User as SocialiteUser;
use Laravel\Socialite\Facades\Socialite;
use Throwable;

/**
 * Sign up / log in with Google or Facebook from the app.
 *
 * The app signs in with the provider's native SDK and sends us its access token;
 * Socialite reads the profile with it (`userFromToken`). Before trusting the token we
 * check it was issued to OUR app, otherwise a token from any other app the person
 * used could log in as them.
 */
class SocialAuthService
{
    private const LABELS = ['google' => 'Google', 'facebook' => 'Facebook'];

    /**
     * Finds (or creates) the user of the provider account behind the token.
     *
     * @return array{0: User, 1: bool} The user and whether the account was just created.
     */
    public function resolveUser(string $provider, string $accessToken): array
    {
        $this->ensureTokenIsForThisApp($provider, $accessToken);

        try {
            /** @var SocialiteUser $profile */
            $profile = Socialite::driver($provider)->stateless()->userFromToken($accessToken);
        } catch (Throwable $e) {
            Log::warning("Social login with {$provider} failed: {$e->getMessage()}");
            $this->fail($provider);
        }

        return DB::transaction(function () use ($provider, $profile) {
            $account = SocialAccount::query()
                ->where('provider', $provider)
                ->where('provider_id', (string) $profile->getId())
                ->first();

            if ($account) {
                return [$account->user, false];
            }

            $email = $profile->getEmail();
            if (! $email) {
                throw ValidationException::withMessages([
                    'access_token' => ['Tu cuenta de '.self::LABELS[$provider].' no comparte un email. Regístrate con tu email y contraseña.'],
                ]);
            }

            // The provider verified the email, so an existing account with it is the same person.
            $user = User::query()->whereRaw('lower(email) = ?', [Str::lower($email)])->first();
            $created = $user === null;

            $user ??= User::create([
                'name' => $profile->getName() ?: Str::before($email, '@'),
                'email' => Str::lower($email),
                'email_verified_at' => now(),
                'avatar_path' => $this->storeAvatar($profile->getAvatar()),
            ]);

            $user->socialAccounts()->create([
                'provider' => $provider,
                'provider_id' => (string) $profile->getId(),
                'email' => $email,
            ]);

            return [$user, $created];
        });
    }

    /**
     * Rejects tokens issued to another app. Skipped while the provider's ids are not configured (local dev).
     */
    private function ensureTokenIsForThisApp(string $provider, string $accessToken): void
    {
        try {
            $issuedTo = match ($provider) {
                'google' => $this->googleAudience($accessToken),
                'facebook' => $this->facebookAppId($accessToken),
            };
        } catch (Throwable $e) {
            Log::warning("Could not verify the {$provider} token: {$e->getMessage()}");
            $this->fail($provider);
        }

        $allowed = $this->allowedAudiences($provider);
        if ($allowed === []) {
            return;
        }

        if ($issuedTo === null || ! in_array($issuedTo, $allowed, true)) {
            $this->fail($provider);
        }
    }

    /**
     * Client id the Google access token was issued to (`azp`), or null when Google rejects it.
     */
    private function googleAudience(string $accessToken): ?string
    {
        if ($this->allowedAudiences('google') === []) {
            return null;
        }

        $response = Http::timeout(10)->get('https://oauth2.googleapis.com/tokeninfo', [
            'access_token' => $accessToken,
        ]);

        return $response->successful() ? ($response->json('azp') ?? $response->json('aud')) : null;
    }

    /**
     * Facebook app the token belongs to, or null when Facebook says it is not valid.
     */
    private function facebookAppId(string $accessToken): ?string
    {
        if ($this->allowedAudiences('facebook') === []) {
            return null;
        }

        $response = Http::timeout(10)->get('https://graph.facebook.com/debug_token', [
            'input_token' => $accessToken,
            'access_token' => config('services.facebook.client_id').'|'.config('services.facebook.client_secret'),
        ]);

        return $response->successful() && $response->json('data.is_valid')
            ? (string) $response->json('data.app_id')
            : null;
    }

    /**
     * Google: the OAuth client ids of the app (Android, iOS, web). Facebook: the app id.
     *
     * @return list<string>
     */
    private function allowedAudiences(string $provider): array
    {
        $ids = $provider === 'google'
            ? array_merge([config('services.google.client_id')], explode(',', (string) config('services.google.allowed_client_ids')))
            : [config('services.facebook.client_id')];

        return array_values(array_filter(array_map(fn ($id) => trim((string) $id), $ids)));
    }

    /**
     * Copies the provider's profile picture to the public disk; the account works without it.
     */
    private function storeAvatar(?string $url): ?string
    {
        if (! $url) {
            return null;
        }

        try {
            $response = Http::timeout(5)->get($url);
            if (! $response->successful() || ! str_starts_with((string) $response->header('Content-Type'), 'image/')) {
                return null;
            }

            $path = 'avatars/'.Str::uuid().'.jpg';
            Storage::disk('public')->put($path, $response->body());

            return $path;
        } catch (Throwable) {
            return null;
        }
    }

    private function fail(string $provider): never
    {
        throw ValidationException::withMessages([
            'access_token' => ['No pudimos validar tu cuenta de '.self::LABELS[$provider].'. Intenta de nuevo.'],
        ]);
    }
}
