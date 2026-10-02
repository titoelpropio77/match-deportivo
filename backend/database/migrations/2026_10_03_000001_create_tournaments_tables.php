<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tournaments organised by a sports center (created from the admin panel), the teams registered
     * in them (paid entry fee) and their games (fixture and results).
     */
    public function up(): void
    {
        Schema::create('tournaments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('court_id')->constrained('courts')->cascadeOnDelete();
            $table->foreignId('sport_id')->constrained('sports')->restrictOnDelete();
            $table->foreignId('level_id')->nullable()->constrained('match_levels')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('name', 120);
            $table->text('description')->nullable();
            $table->text('rules')->nullable();
            // league (todos contra todos), knockout (eliminación directa), groups_knockout (grupos + eliminación).
            $table->string('format', 20)->default('league');
            $table->string('gender', 10)->default('mixed');
            // Entry fee per team (Bs). 0 = free.
            $table->decimal('entry_fee', 10, 2)->default(0);
            $table->text('prizes')->nullable();
            $table->unsignedSmallInteger('max_teams');
            $table->unsignedSmallInteger('min_players_per_team')->default(1);
            $table->unsignedSmallInteger('max_players_per_team')->nullable();
            $table->dateTime('registration_closes_at');
            $table->date('starts_on');
            $table->date('ends_on')->nullable();
            // draft, open, closed, in_progress, finished, cancelled.
            $table->string('status', 20)->default('draft');
            $table->string('cover_path')->nullable();
            $table->timestamps();

            $table->index(['status', 'starts_on']);
        });

        Schema::create('tournament_registrations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tournament_id')->constrained('tournaments')->cascadeOnDelete();
            $table->foreignId('team_id')->constrained('teams')->cascadeOnDelete();
            $table->foreignId('registered_by')->nullable()->constrained('users')->nullOnDelete();
            // pending_payment, confirmed, cancelled.
            $table->string('status', 20)->default('pending_payment');
            $table->decimal('amount', 10, 2)->default(0);
            $table->string('payment_method', 20)->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('cancellation_reason')->nullable();
            $table->timestamp('refunded_at')->nullable();
            $table->timestamps();

            $table->unique(['tournament_id', 'team_id']);
        });

        Schema::create('tournament_games', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tournament_id')->constrained('tournaments')->cascadeOnDelete();
            // "Fecha 1", "Cuartos de final", "Final"...
            $table->string('round', 40);
            $table->unsignedSmallInteger('round_order')->default(1);
            $table->foreignId('home_team_id')->nullable()->constrained('teams')->nullOnDelete();
            $table->foreignId('away_team_id')->nullable()->constrained('teams')->nullOnDelete();
            $table->foreignId('court_field_id')->nullable()->constrained('court_fields')->nullOnDelete();
            $table->dateTime('scheduled_at')->nullable();
            $table->unsignedSmallInteger('home_score')->nullable();
            $table->unsignedSmallInteger('away_score')->nullable();
            // scheduled, played, cancelled.
            $table->string('status', 20)->default('scheduled');
            $table->timestamps();

            $table->index(['tournament_id', 'round_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tournament_games');
        Schema::dropIfExists('tournament_registrations');
        Schema::dropIfExists('tournaments');
    }
};
