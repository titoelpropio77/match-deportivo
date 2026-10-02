<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Teams of players for one sport. A team can be added to a match: all its members join it.
     */
    public function up(): void
    {
        Schema::create('teams', function (Blueprint $table) {
            $table->id();
            // Creator and captain; the only one who edits the team and manages its members.
            $table->foreignId('owner_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('sport_id')->constrained('sports')->restrictOnDelete();
            $table->foreignId('level_id')->nullable()->constrained('match_levels')->nullOnDelete();
            $table->string('name', 60);
            // Abbreviation for scoreboards and small badges, e.g. "TIG".
            $table->string('short_name', 4)->nullable();
            $table->string('gender', 10)->default('mixed');
            // Team colour as "#RRGGBB", used for the badge when there is no logo.
            $table->string('primary_color', 7)->nullable();
            $table->text('description')->nullable();
            $table->string('logo_path')->nullable();
            $table->timestamps();

            $table->index(['sport_id', 'name']);
        });

        Schema::create('team_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained('teams')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('role', 10)->default('player');
            $table->unsignedTinyInteger('jersey_number')->nullable();
            $table->string('position', 40)->nullable();
            $table->timestamps();

            $table->unique(['team_id', 'user_id']);
        });

        // Teams invited to a match (their members are added as players).
        Schema::create('match_team', function (Blueprint $table) {
            $table->foreignId('match_id')->constrained('matches')->cascadeOnDelete();
            $table->foreignId('team_id')->constrained('teams')->cascadeOnDelete();

            $table->primary(['match_id', 'team_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('match_team');
        Schema::dropIfExists('team_members');
        Schema::dropIfExists('teams');
    }
};
