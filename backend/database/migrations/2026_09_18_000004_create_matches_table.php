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
        Schema::create('matches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organizer_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('sport_id')->constrained('sports')->restrictOnDelete();
            $table->foreignId('level_id')->constrained('match_levels')->restrictOnDelete();
            $table->foreignId('court_id')->constrained('courts')->restrictOnDelete();
            $table->dateTime('scheduled_at');
            $table->dateTime('start_time');
            $table->dateTime('end_time');
            $table->unsignedSmallInteger('total_players');
            $table->unsignedSmallInteger('missing_players');
            $table->unsignedSmallInteger('max_players');
            $table->enum('status', ['open', 'full', 'cancelled', 'finished'])->default('open');
            $table->timestamps();

            $table->index(['status', 'scheduled_at']);
            $table->index(['sport_id', 'scheduled_at']);
            $table->index(['court_id', 'scheduled_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('matches');
    }
};