<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('rating_tags', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sport_id')->constrained()->cascadeOnDelete();
            $table->string('key');
            $table->string('label');
            $table->string('polarity', 16);
            $table->boolean('marks_absence')->default(false);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['sport_id', 'key']);
        });

        Schema::create('player_ratings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('match_id')->constrained('matches')->cascadeOnDelete();
            $table->foreignId('reviewer_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedTinyInteger('stars')->nullable();
            $table->boolean('did_not_attend')->default(false);
            $table->timestamps();

            $table->unique(['match_id', 'user_id']);
        });

        Schema::create('player_rating_tag', function (Blueprint $table) {
            $table->id();
            $table->foreignId('player_rating_id')->constrained()->cascadeOnDelete();
            $table->foreignId('rating_tag_id')->constrained()->cascadeOnDelete();

            $table->unique(['player_rating_id', 'rating_tag_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('player_rating_tag');
        Schema::dropIfExists('player_ratings');
        Schema::dropIfExists('rating_tags');
    }
};
