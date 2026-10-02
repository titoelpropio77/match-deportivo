<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Sports gear a sports center rents with its courts (balls, rackets...), tied to one sport,
     * and the gear added to each court reservation.
     */
    public function up(): void
    {
        Schema::create('rental_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('court_id')->constrained('courts')->cascadeOnDelete();
            $table->foreignId('sport_id')->constrained('sports')->restrictOnDelete();
            $table->string('name', 100);
            $table->string('description', 500)->nullable();
            $table->decimal('price', 8, 2);
            // per_hour: price × hours of the reservation; flat: price once per reservation.
            $table->string('price_type', 10)->default('per_hour');
            // Units the center owns; null = unlimited.
            $table->unsignedSmallInteger('stock')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('court_reservation_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('court_reservation_id')->constrained('court_reservations')->cascadeOnDelete();
            // Kept nullable so the history survives when the center deletes the item.
            $table->foreignId('rental_item_id')->nullable()->constrained('rental_items')->nullOnDelete();
            $table->string('name', 100);
            $table->unsignedSmallInteger('quantity');
            $table->decimal('unit_price', 8, 2);
            $table->string('price_type', 10);
            $table->decimal('amount', 8, 2);
            $table->timestamps();
        });

        Schema::table('court_reservations', function (Blueprint $table) {
            // Part of `amount` that comes from rented gear (amount = court + gear).
            $table->decimal('items_amount', 8, 2)->default(0)->after('amount');
        });
    }

    public function down(): void
    {
        Schema::table('court_reservations', function (Blueprint $table) {
            $table->dropColumn('items_amount');
        });
        Schema::dropIfExists('court_reservation_items');
        Schema::dropIfExists('rental_items');
    }
};
