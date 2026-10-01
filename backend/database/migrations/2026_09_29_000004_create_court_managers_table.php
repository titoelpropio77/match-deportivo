<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Managers of a venue (role "manager" in the admin panel): staff the owner assigns with limited permissions.
     */
    public function up(): void
    {
        Schema::create('court_managers', function (Blueprint $table) {
            $table->foreignId('court_id')->constrained('courts')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            $table->primary(['court_id', 'user_id']);
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('court_managers');
    }
};
