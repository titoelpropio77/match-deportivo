<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Physical court details: size ("4x4"), free-text description and amenities (keys of CourtFieldFeature).
     */
    public function up(): void
    {
        Schema::table('court_fields', function (Blueprint $table) {
            $table->string('dimensions', 50)->nullable()->after('price_per_hour');
            $table->text('description')->nullable()->after('dimensions');
            $table->json('features')->nullable()->after('description');
        });
    }

    public function down(): void
    {
        Schema::table('court_fields', function (Blueprint $table) {
            $table->dropColumn(['dimensions', 'description', 'features']);
        });
    }
};
