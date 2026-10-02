<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Event spaces of a sports center (grill areas, quinchos, halls...) rented by the hour for
     * gatherings, and their reservations. Managed by the partner from the admin panel.
     */
    public function up(): void
    {
        Schema::create('event_spaces', function (Blueprint $table) {
            $table->id();
            $table->foreignId('court_id')->constrained('courts')->cascadeOnDelete();
            $table->string('name', 120);
            // Key of App\Enums\EventSpaceType (grill, quincho, hall, terrace, garden).
            $table->string('type', 20);
            $table->text('description')->nullable();
            $table->decimal('price_per_hour', 8, 2);
            // Maximum number of guests.
            $table->unsignedSmallInteger('capacity');
            $table->unsignedTinyInteger('min_hours')->default(1);
            // Keys of App\Enums\EventSpaceAmenity.
            $table->json('amenities')->nullable();
            // House rules shown before booking (music, decoration, cleaning...).
            $table->text('rules')->nullable();
            // Path on the public disk (uploaded from the admin panel) or an external URL (seeders).
            $table->string('photo_path')->nullable();
            // Own schedule when it differs from the sports center's (null = same as the center).
            $table->time('opening_time')->nullable();
            $table->time('closing_time')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('event_space_reservations', function (Blueprint $table) {
            $table->id();
            // "E" + 7 characters: payment reference shown with the QR.
            $table->string('code', 12)->unique();
            $table->foreignId('event_space_id')->constrained('event_spaces')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('customer_name')->nullable();
            $table->string('customer_phone', 30)->nullable();
            $table->date('reserved_on');
            $table->time('starts_at');
            $table->time('ends_at');
            $table->unsignedTinyInteger('hours');
            $table->unsignedSmallInteger('guests');
            // Key of App\Enums\EventKind (birthday, meeting, barbecue...).
            $table->string('event_type', 20)->nullable();
            $table->decimal('amount', 10, 2);
            // pending_payment, confirmed, paid, cancelled (same rules as court_reservations).
            $table->string('status', 20)->default('pending_payment');
            $table->string('source', 20)->default('app');
            $table->string('payment_method', 20)->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('cancellation_reason')->nullable();
            $table->timestamp('refunded_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['event_space_id', 'reserved_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_space_reservations');
        Schema::dropIfExists('event_spaces');
    }
};
