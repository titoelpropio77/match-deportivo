<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Physical courts (court_fields) of the venue where a match is played; a match may use several.
     */
    public function up(): void
    {
        Schema::create('match_court_field', function (Blueprint $table) {
            $table->foreignId('match_id')->constrained('matches')->cascadeOnDelete();
            $table->foreignId('court_field_id')->constrained('court_fields')->cascadeOnDelete();

            $table->primary(['match_id', 'court_field_id']);
            $table->index('court_field_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('match_court_field');
    }
};
