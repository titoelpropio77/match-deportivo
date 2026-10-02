<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Per-hour extras of a physical court: air conditioning (chosen by the player) and night lighting
     * (charged automatically for the hours from lighting_from). Reservations keep what they were charged.
     */
    public function up(): void
    {
        Schema::table('court_fields', function (Blueprint $table) {
            $table->decimal('air_conditioning_price', 10, 2)->nullable()->after('features');
            $table->decimal('lighting_price', 10, 2)->nullable()->after('air_conditioning_price');
            $table->time('lighting_from')->nullable()->after('lighting_price');
        });

        Schema::table('court_reservations', function (Blueprint $table) {
            $table->boolean('air_conditioning')->default(false)->after('items_amount');
            $table->decimal('air_conditioning_amount', 10, 2)->default(0)->after('air_conditioning');
            $table->decimal('lighting_amount', 10, 2)->default(0)->after('air_conditioning_amount');
        });
    }

    public function down(): void
    {
        Schema::table('court_reservations', function (Blueprint $table) {
            $table->dropColumn(['air_conditioning', 'air_conditioning_amount', 'lighting_amount']);
        });

        Schema::table('court_fields', function (Blueprint $table) {
            $table->dropColumn(['air_conditioning_price', 'lighting_price', 'lighting_from']);
        });
    }
};
