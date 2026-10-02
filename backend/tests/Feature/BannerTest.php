<?php

namespace Tests\Feature;

use App\Models\Banner;
use App\Models\Court;
use App\Models\Sport;
use App\Models\Tournament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BannerTest extends TestCase
{
    use RefreshDatabase;

    public function test_lists_visible_banners_in_order_with_their_link(): void
    {
        $court = $this->makeCourt();
        Banner::query()->create(['title' => 'Segundo', 'link_type' => 'court', 'link_id' => $court->id, 'sort_order' => 20]);
        Banner::query()->create(['title' => 'Primero', 'link_type' => 'reserve_courts', 'sort_order' => 10]);
        Banner::query()->create(['title' => 'Externo', 'link_type' => 'url', 'link_url' => 'https://example.com', 'sort_order' => 30]);

        $this->getJson('/api/banners')
            ->assertOk()
            ->assertJsonCount(3, 'data')
            ->assertJsonPath('data.0.title', 'Primero')
            ->assertJsonPath('data.0.link.type', 'reserve_courts')
            ->assertJsonPath('data.0.link.id', null)
            ->assertJsonPath('data.1.link', ['type' => 'court', 'id' => $court->id, 'url' => null])
            ->assertJsonPath('data.2.link.url', 'https://example.com');
    }

    public function test_hides_inactive_and_out_of_window_banners(): void
    {
        Banner::query()->create(['title' => 'Inactivo', 'is_active' => false]);
        Banner::query()->create(['title' => 'Futuro', 'starts_at' => now()->addDay()]);
        Banner::query()->create(['title' => 'Vencido', 'ends_at' => now()->subDay()]);
        Banner::query()->create(['title' => 'Vigente', 'starts_at' => now()->subDay(), 'ends_at' => now()->addDay()]);

        $this->getJson('/api/banners')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', 'Vigente');
    }

    public function test_hides_banners_whose_target_is_missing_or_not_public(): void
    {
        $court = $this->makeCourt();
        $draft = Tournament::query()->create([
            'court_id' => $court->id,
            'sport_id' => Sport::query()->create(['key' => 'padel', 'name' => 'Pádel'])->id,
            'name' => 'Borrador',
            'max_teams' => 8,
            'registration_closes_at' => now()->addWeek(),
            'starts_on' => now()->addWeeks(2),
            'status' => Tournament::STATUS_DRAFT,
        ]);
        Banner::query()->create(['title' => 'Torneo borrador', 'link_type' => 'tournament', 'link_id' => $draft->id]);
        Banner::query()->create(['title' => 'Centro borrado', 'link_type' => 'court', 'link_id' => 999]);
        Banner::query()->create(['title' => 'URL vacía', 'link_type' => 'url']);

        $this->getJson('/api/banners')->assertOk()->assertJsonCount(0, 'data');

        $draft->update(['status' => Tournament::STATUS_OPEN]);
        $this->getJson('/api/banners')->assertOk()->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', 'Torneo borrador');
    }

    private function makeCourt(): Court
    {
        return Court::query()->create([
            'name' => 'Centro',
            'address' => 'Santa Cruz',
            'latitude' => -17.78,
            'longitude' => -63.18,
            'opening_time' => '08:00',
            'closing_time' => '22:00',
        ]);
    }
}
