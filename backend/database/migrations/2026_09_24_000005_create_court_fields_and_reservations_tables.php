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
        Schema::create('court_fields', function (Blueprint $table) {
            $table->id();
            $table->foreignId('court_id')->constrained('courts')->cascadeOnDelete();
            $table->string('name');
            $table->decimal('price_per_hour', 8, 2);
            $table->timestamps();
        });

        Schema::create('court_field_sport', function (Blueprint $table) {
            $table->foreignId('court_field_id')->constrained('court_fields')->cascadeOnDelete();
            $table->foreignId('sport_id')->constrained('sports')->cascadeOnDelete();

            $table->primary(['court_field_id', 'sport_id']);
        });

        Schema::create('court_reservations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('court_field_id')->constrained('court_fields')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('sport_id')->constrained('sports')->restrictOnDelete();
            $table->date('reserved_on');
            $table->time('starts_at');
            $table->time('ends_at');
            $table->unsignedTinyInteger('hours');
            $table->decimal('amount', 8, 2);
            $table->string('status')->default('pending_payment');
            $table->timestamps();

            $table->index(['court_field_id', 'reserved_on']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('court_reservations');
        Schema::dropIfExists('court_field_sport');
        Schema::dropIfExists('court_fields');
    }
};
