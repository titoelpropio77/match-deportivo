<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Product categories offered by default (catalog editable from the admin panel).
     */
    private const CATEGORIES = [
        ['key' => 'balls', 'name' => 'Balones y pelotas', 'icon' => 'fas fa-futbol'],
        ['key' => 'rackets', 'name' => 'Raquetas y paletas', 'icon' => 'fas fa-table-tennis'],
        ['key' => 'footwear', 'name' => 'Calzado', 'icon' => 'fas fa-shoe-prints'],
        ['key' => 'apparel', 'name' => 'Ropa deportiva', 'icon' => 'fas fa-tshirt'],
        ['key' => 'protection', 'name' => 'Protección', 'icon' => 'fas fa-shield-alt'],
        ['key' => 'accessories', 'name' => 'Accesorios', 'icon' => 'fas fa-shopping-bag'],
        ['key' => 'training', 'name' => 'Entrenamiento', 'icon' => 'fas fa-dumbbell'],
        ['key' => 'drinks', 'name' => 'Bebidas', 'icon' => 'fas fa-glass-whiskey'],
        ['key' => 'snacks', 'name' => 'Snacks', 'icon' => 'fas fa-cookie-bite'],
        ['key' => 'supplements', 'name' => 'Suplementos', 'icon' => 'fas fa-capsules'],
    ];

    /**
     * Stores of a sports center (one or more), their products with stock, the sales and the
     * stock ledger. Created by the partner/admin, run by the venue managers from the admin panel.
     */
    public function up(): void
    {
        Schema::create('product_categories', function (Blueprint $table) {
            $table->id();
            $table->string('key', 50)->unique();
            $table->string('name', 100)->unique();
            // Font Awesome class shown in the admin panel, e.g. "fas fa-futbol".
            $table->string('icon', 50)->nullable();
            $table->timestamps();
        });

        Schema::create('stores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('court_id')->constrained('courts')->cascadeOnDelete();
            $table->string('name', 120);
            $table->text('description')->nullable();
            // Contact (WhatsApp) shown in the app.
            $table->string('phone', 30)->nullable();
            // Path on the backend's public disk (stores/{id}/…) or an external URL (seeders).
            $table->string('cover_path')->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        // What kind of products the store sells.
        Schema::create('product_category_store', function (Blueprint $table) {
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_category_id')->constrained()->cascadeOnDelete();
            $table->primary(['store_id', 'product_category_id']);
        });

        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            // A category in use cannot be deleted from the catalog.
            $table->foreignId('product_category_id')->constrained()->restrictOnDelete();
            $table->string('name', 150);
            $table->string('sku', 60)->nullable();
            $table->text('description')->nullable();
            // List price; the sale price applies discount_percent.
            $table->decimal('price', 10, 2);
            $table->unsignedTinyInteger('discount_percent')->default(0);
            // Units physically in the store. Only changes through stock_movements.
            $table->unsignedInteger('stock')->default(0);
            // "Low stock" warning in the panel when stock <= this value (null = no warning).
            $table->unsignedInteger('min_stock')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['store_id', 'is_active']);
        });

        Schema::create('product_photos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            // Path on the backend's public disk (products/{id}/…) or an external URL (seeders).
            $table->string('path');
            $table->unsignedSmallInteger('order')->default(0);
            $table->timestamps();
        });

        Schema::create('store_orders', function (Blueprint $table) {
            $table->id();
            // "T" + 7 characters: payment reference shown with the QR and at pickup.
            $table->string('code', 12)->unique();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('customer_name')->nullable();
            $table->string('customer_phone', 30)->nullable();
            // List price total, discount and what the customer pays.
            $table->decimal('subtotal', 10, 2);
            $table->decimal('discount_amount', 10, 2)->default(0);
            $table->decimal('total', 10, 2);
            // pending_payment (app, holds the units 15 min), paid, cancelled.
            $table->string('status', 20)->default('pending_payment');
            $table->string('source', 20)->default('app');
            $table->string('payment_method', 20)->nullable();
            $table->timestamp('paid_at')->nullable();
            // Handed to the customer at the store.
            $table->timestamp('delivered_at')->nullable();
            $table->foreignId('delivered_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('cancelled_at')->nullable();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('cancellation_reason')->nullable();
            $table->timestamp('refunded_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['store_id', 'status']);
        });

        Schema::create('store_order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            // Copy of the product at the time of the sale, so history does not change when it is edited.
            $table->string('name', 150);
            $table->decimal('list_price', 10, 2);
            $table->unsignedTinyInteger('discount_percent')->default(0);
            $table->decimal('unit_price', 10, 2);
            $table->unsignedInteger('quantity');
            $table->decimal('amount', 10, 2);
            $table->timestamps();
        });

        // Every change of products.stock: restock, loss, count adjustment, sale, return...
        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('store_order_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('type', 20);
            // Signed: positive adds units, negative takes them out.
            $table->integer('quantity');
            $table->unsignedInteger('stock_after');
            $table->string('reason')->nullable();
            $table->timestamps();

            $table->index(['product_id', 'created_at']);
        });

        $now = now();
        DB::table('product_categories')->insert(array_map(
            fn (array $category) => [...$category, 'created_at' => $now, 'updated_at' => $now],
            self::CATEGORIES,
        ));
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_movements');
        Schema::dropIfExists('store_order_items');
        Schema::dropIfExists('store_orders');
        Schema::dropIfExists('product_photos');
        Schema::dropIfExists('products');
        Schema::dropIfExists('product_category_store');
        Schema::dropIfExists('stores');
        Schema::dropIfExists('product_categories');
    }
};
