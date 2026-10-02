<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Court booking (court_reservations.booking_code) the match was created from, if any.
     */
    public function up(): void
    {
        Schema::table('matches', function (Blueprint $table) {
            $table->string('booking_code', 20)->nullable()->after('court_id')->index();
        });
    }

    public function down(): void
    {
        Schema::table('matches', function (Blueprint $table) {
            $table->dropColumn('booking_code');
        });
    }
};
