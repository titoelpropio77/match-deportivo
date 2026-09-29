<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('match_players', function (Blueprint $table) {
            $table->string('status', 20)->default('pending')->after('quantity_slots');
        });

        DB::table('match_players')->update(['status' => 'confirmed']);

        Schema::create('trusted_players', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organizer_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('player_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['organizer_id', 'player_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('trusted_players');

        Schema::table('match_players', function (Blueprint $table) {
            $table->dropColumn('status');
        });
    }
};
