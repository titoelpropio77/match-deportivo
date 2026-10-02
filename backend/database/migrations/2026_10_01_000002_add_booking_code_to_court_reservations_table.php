<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Groups the reservations booked (and paid with one QR) together: several courts and/or hour ranges.
     */
    public function up(): void
    {
        Schema::table('court_reservations', function (Blueprint $table) {
            $table->string('booking_code', 20)->nullable()->after('id')->index();
        });
    }

    public function down(): void
    {
        Schema::table('court_reservations', function (Blueprint $table) {
            $table->dropColumn('booking_code');
        });
    }
};
