<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Amenities a physical court can have, managed from the admin panel instead of an enum.
     * "air_conditioning" and "lighting" drive the per-hour surcharges, so their keys are fixed.
     */
    private const DEFAULTS = [
        ['key' => 'air_conditioning', 'name' => 'Aire acondicionado', 'icon' => 'fas fa-snowflake'],
        ['key' => 'covered', 'name' => 'Techada', 'icon' => 'fas fa-home'],
        ['key' => 'lighting', 'name' => 'Iluminación nocturna', 'icon' => 'fas fa-lightbulb'],
        ['key' => 'stands', 'name' => 'Graderías', 'icon' => 'fas fa-users'],
    ];

    public function up(): void
    {
        Schema::create('court_features', function (Blueprint $table) {
            $table->id();
            $table->string('key', 50)->unique();
            $table->string('name', 100);
            // Font Awesome class shown next to the name, e.g. "fas fa-snowflake".
            $table->string('icon', 50)->nullable();
            $table->timestamps();
        });

        Schema::create('court_field_feature', function (Blueprint $table) {
            $table->foreignId('court_field_id')->constrained()->cascadeOnDelete();
            $table->foreignId('court_feature_id')->constrained()->cascadeOnDelete();
            $table->primary(['court_field_id', 'court_feature_id']);
        });

        $now = now();
        DB::table('court_features')->insert(array_map(fn (array $feature) => [...$feature, 'created_at' => $now, 'updated_at' => $now], self::DEFAULTS));
        $featureIds = DB::table('court_features')->pluck('id', 'key');

        // Move the keys stored in court_fields.features (JSON) to the pivot.
        DB::table('court_fields')->whereNotNull('features')->select(['id', 'features'])->orderBy('id')->each(function (object $field) use ($featureIds): void {
            $rows = collect(json_decode($field->features, true) ?: [])
                ->unique()
                ->filter(fn ($key) => $featureIds->has($key))
                ->map(fn (string $key) => ['court_field_id' => $field->id, 'court_feature_id' => $featureIds[$key]])
                ->values()
                ->all();
            DB::table('court_field_feature')->insert($rows);
        });

        Schema::table('court_fields', function (Blueprint $table) {
            $table->dropColumn('features');
        });
    }

    public function down(): void
    {
        Schema::table('court_fields', function (Blueprint $table) {
            $table->json('features')->nullable()->after('description');
        });

        DB::table('court_field_feature')
            ->join('court_features', 'court_features.id', '=', 'court_field_feature.court_feature_id')
            ->get(['court_field_feature.court_field_id', 'court_features.key'])
            ->groupBy('court_field_id')
            ->each(fn ($features, $fieldId) => DB::table('court_fields')->where('id', $fieldId)->update([
                'features' => json_encode($features->pluck('key')->values()->all()),
            ]));

        Schema::dropIfExists('court_field_feature');
        Schema::dropIfExists('court_features');
    }
};
