<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * What an event space includes (grill, restrooms...), managed from the admin panel instead of an enum.
     */
    private const DEFAULTS = [
        ['key' => 'grill', 'name' => 'Parrilla', 'icon' => 'fas fa-fire'],
        ['key' => 'tables_chairs', 'name' => 'Mesas y sillas', 'icon' => 'fas fa-chair'],
        ['key' => 'restrooms', 'name' => 'Baños', 'icon' => 'fas fa-restroom'],
        ['key' => 'fridge', 'name' => 'Refrigerador', 'icon' => 'fas fa-snowflake'],
        ['key' => 'kitchen', 'name' => 'Cocina', 'icon' => 'fas fa-utensils'],
        ['key' => 'sound', 'name' => 'Equipo de sonido', 'icon' => 'fas fa-music'],
        ['key' => 'air_conditioning', 'name' => 'Aire acondicionado', 'icon' => 'fas fa-wind'],
        ['key' => 'covered', 'name' => 'Techado', 'icon' => 'fas fa-home'],
        ['key' => 'lighting', 'name' => 'Iluminación', 'icon' => 'fas fa-lightbulb'],
        ['key' => 'pool', 'name' => 'Piscina', 'icon' => 'fas fa-swimmer'],
        ['key' => 'kids_area', 'name' => 'Área de niños', 'icon' => 'fas fa-child'],
        ['key' => 'parking', 'name' => 'Parqueo', 'icon' => 'fas fa-parking'],
        ['key' => 'wifi', 'name' => 'Wi-Fi', 'icon' => 'fas fa-wifi'],
    ];

    public function up(): void
    {
        Schema::create('event_amenities', function (Blueprint $table) {
            $table->id();
            $table->string('key', 50)->unique();
            $table->string('name', 100);
            // Font Awesome class shown next to the name, e.g. "fas fa-fire".
            $table->string('icon', 50)->nullable();
            $table->timestamps();
        });

        Schema::create('event_space_amenity', function (Blueprint $table) {
            $table->foreignId('event_space_id')->constrained()->cascadeOnDelete();
            $table->foreignId('event_amenity_id')->constrained()->cascadeOnDelete();
            $table->primary(['event_space_id', 'event_amenity_id']);
        });

        $now = now();
        DB::table('event_amenities')->insert(array_map(fn (array $amenity) => [...$amenity, 'created_at' => $now, 'updated_at' => $now], self::DEFAULTS));
        $amenityIds = DB::table('event_amenities')->pluck('id', 'key');

        // Move the keys stored in event_spaces.amenities (JSON) to the pivot.
        DB::table('event_spaces')->whereNotNull('amenities')->select(['id', 'amenities'])->orderBy('id')->each(function (object $space) use ($amenityIds): void {
            $rows = collect(json_decode($space->amenities, true) ?: [])
                ->unique()
                ->filter(fn ($key) => $amenityIds->has($key))
                ->map(fn (string $key) => ['event_space_id' => $space->id, 'event_amenity_id' => $amenityIds[$key]])
                ->values()
                ->all();
            DB::table('event_space_amenity')->insert($rows);
        });

        Schema::table('event_spaces', function (Blueprint $table) {
            $table->dropColumn('amenities');
        });
    }

    public function down(): void
    {
        Schema::table('event_spaces', function (Blueprint $table) {
            $table->json('amenities')->nullable()->after('min_hours');
        });

        DB::table('event_space_amenity')
            ->join('event_amenities', 'event_amenities.id', '=', 'event_space_amenity.event_amenity_id')
            ->get(['event_space_amenity.event_space_id', 'event_amenities.key'])
            ->groupBy('event_space_id')
            ->each(fn ($amenities, $spaceId) => DB::table('event_spaces')->where('id', $spaceId)->update([
                'amenities' => json_encode($amenities->pluck('key')->values()->all()),
            ]));

        Schema::dropIfExists('event_space_amenity');
        Schema::dropIfExists('event_amenities');
    }
};
