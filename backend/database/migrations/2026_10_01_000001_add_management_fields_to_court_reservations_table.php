<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Venue-side management of reservations from the admin panel: walk-in bookings without an app
     * user (customer_name/phone), payment tracking, cancellation by the venue and refunds.
     */
    public function up(): void
    {
        Schema::table('court_reservations', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->change();
            $table->string('customer_name')->nullable()->after('user_id');
            $table->string('customer_phone', 30)->nullable()->after('customer_name');
            // app: booked by a player from the app; admin: registered by venue staff (phone, walk-in).
            $table->string('source', 20)->default('app')->after('status');
            $table->string('payment_method', 20)->nullable()->after('source');
            $table->timestamp('paid_at')->nullable()->after('payment_method');
            $table->timestamp('cancelled_at')->nullable()->after('paid_at');
            $table->foreignId('cancelled_by')->nullable()->after('cancelled_at')->constrained('users')->nullOnDelete();
            $table->text('cancellation_reason')->nullable()->after('cancelled_by');
            $table->timestamp('refunded_at')->nullable()->after('cancellation_reason');
            $table->foreignId('created_by')->nullable()->after('refunded_at')->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable()->after('created_by');
        });
    }

    public function down(): void
    {
        Schema::table('court_reservations', function (Blueprint $table) {
            $table->dropConstrainedForeignId('created_by');
            $table->dropConstrainedForeignId('cancelled_by');
            $table->dropColumn([
                'customer_name',
                'customer_phone',
                'source',
                'payment_method',
                'paid_at',
                'cancelled_at',
                'cancellation_reason',
                'refunded_at',
                'notes',
            ]);
        });
    }
};
