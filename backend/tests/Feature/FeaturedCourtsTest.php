<?php

namespace Tests\Feature;

use App\Models\Court;
use App\Models\CourtReview;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FeaturedCourtsTest extends TestCase
{
    use RefreshDatabase;

    public function test_best_rated_come_first_then_new_ones(): void
    {
        $old = $this->makeCourt('Antiguo sin reseñas', createdDaysAgo: 90);
        $new = $this->makeCourt('Recién abierto', createdDaysAgo: 3);
        $top = $this->makeCourt('El mejor', createdDaysAgo: 120);
        $this->review($top, 5);
        $this->review($top, 4);
        $lowRated = $this->makeCourt('Mal calificado', createdDaysAgo: 100);
        $this->review($lowRated, 2);

        $response = $this->getJson('/api/courts/featured')->assertOk();

        $response->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.name', 'El mejor')
            ->assertJsonPath('data.0.highlight', 'top_rated')
            ->assertJsonPath('data.0.rating', 4.5)
            ->assertJsonPath('data.0.reviews_count', 2)
            ->assertJsonPath('data.1.name', 'Recién abierto')
            ->assertJsonPath('data.1.highlight', 'new')
            ->assertJsonPath('data.1.is_new', true);
        $this->assertNotContains($old->id, collect($response->json('data'))->pluck('id'));
    }

    public function test_imported_review_data_counts_until_players_review(): void
    {
        $this->makeCourt('Con review_data', createdDaysAgo: 200, reviewData: ['average' => 4.7, 'count' => 20]);

        $this->getJson('/api/courts/featured')
            ->assertOk()
            ->assertJsonPath('data.0.rating', 4.7)
            ->assertJsonPath('data.0.reviews_count', 20)
            ->assertJsonPath('data.0.highlight', 'top_rated');
    }

    public function test_falls_back_to_any_center_when_none_qualifies(): void
    {
        $this->makeCourt('Centro común', createdDaysAgo: 200);

        $this->getJson('/api/courts/featured')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Centro común')
            ->assertJsonPath('data.0.highlight', null)
            ->assertJsonPath('data.0.rating', null);
    }

    public function test_includes_lowest_price_and_first_photo(): void
    {
        $court = $this->makeCourt('Con canchas', createdDaysAgo: 1);
        $court->fields()->create(['name' => 'Cancha 1', 'price_per_hour' => 90]);
        $court->fields()->create(['name' => 'Cancha 2', 'price_per_hour' => 60]);
        $court->photos()->create(['url' => 'https://example.com/a.jpg', 'order' => 0]);

        $this->getJson('/api/courts/featured')
            ->assertOk()
            ->assertJsonPath('data.0.min_price', 60)
            ->assertJsonPath('data.0.photo_url', 'https://example.com/a.jpg');
    }

    /**
     * @param  array<string, mixed>|null  $reviewData
     */
    private function makeCourt(string $name, int $createdDaysAgo, ?array $reviewData = null): Court
    {
        $court = Court::query()->create([
            'name' => $name,
            'address' => 'Santa Cruz',
            'latitude' => -17.78,
            'longitude' => -63.18,
            'opening_time' => '08:00',
            'closing_time' => '22:00',
            'review_data' => $reviewData,
        ]);
        $court->forceFill(['created_at' => now()->subDays($createdDaysAgo)])->save();

        return $court;
    }

    private function review(Court $court, int $rate): void
    {
        CourtReview::query()->create([
            'court_id' => $court->id,
            'user_id' => User::factory()->create()->id,
            'rate' => $rate,
        ]);
    }
}
