<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Sports a player likes ("Mis deportes favoritos"), chosen when registering or editing the profile.
     */
    public function up(): void
    {
        Schema::create('user_favorite_sports', function (Blueprint $table) {
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('sport_id')->constrained('sports')->cascadeOnDelete();
            $table->timestamps();

            $table->primary(['user_id', 'sport_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_favorite_sports');
    }
};
