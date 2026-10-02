<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Slides of the home carousel in the app, managed from the admin panel.
     */
    public function up(): void
    {
        Schema::create('banners', function (Blueprint $table) {
            $table->id();
            $table->string('title', 80);
            $table->string('subtitle', 160)->nullable();
            $table->string('button_label', 30)->nullable();
            $table->string('image_path')->nullable();
            // "#RRGGBB" background used when there is no image (or while it loads).
            $table->string('background_color', 7)->default('#4F46E5');
            // Where tapping the banner leads in the app: see App\Models\Banner::LINK_TYPES.
            $table->string('link_type', 30)->default('none');
            // Target record for tournament / court links.
            $table->unsignedBigInteger('link_id')->nullable();
            // External address for url links.
            $table->string('link_url', 500)->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            // Optional publication window.
            $table->dateTime('starts_at')->nullable();
            $table->dateTime('ends_at')->nullable();
            $table->timestamps();

            $table->index(['is_active', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('banners');
    }
};
