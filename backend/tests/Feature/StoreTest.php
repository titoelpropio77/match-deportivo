<?php

namespace Tests\Feature;

use App\Models\Court;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\StockMovement;
use App\Models\Store;
use App\Models\StoreOrder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class StoreTest extends TestCase
{
    use RefreshDatabase;

    private Store $store;

    private Product $ball;

    private Product $drink;

    protected function setUp(): void
    {
        parent::setUp();

        $court = Court::query()->create([
            'name' => 'Complejo Wally Sur',
            'address' => 'Santa Cruz',
            'latitude' => -17.7,
            'longitude' => -63.1,
            'opening_time' => '08:00',
            'closing_time' => '22:00',
        ]);
        // Categories are seeded by the migration.
        $categories = ProductCategory::query()->pluck('id', 'key');

        $this->store = Store::query()->create(['court_id' => $court->id, 'name' => 'Wally Shop']);
        $this->store->categories()->sync([$categories['balls'], $categories['drinks']]);

        $this->ball = $this->product(['product_category_id' => $categories['balls'], 'name' => 'Pelota de wally', 'price' => 120, 'discount_percent' => 10], 5);
        $this->drink = $this->product(['product_category_id' => $categories['drinks'], 'name' => 'Isotónica', 'price' => 12], 10);
    }

    private function product(array $attributes, int $stock): Product
    {
        $product = $this->store->products()->create($attributes);
        $product->moveStock($stock, StockMovement::TYPE_INITIAL);

        return $product;
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(array $items): array
    {
        return [
            'store_id' => $this->store->id,
            'items' => array_map(fn (array $item) => ['product_id' => $item[0]->id, 'quantity' => $item[1]], $items),
        ];
    }

    public function test_active_stores_are_listed_with_their_categories_and_filtered_by_category(): void
    {
        $other = Store::query()->create(['court_id' => $this->store->court_id, 'name' => 'Tienda cerrada', 'is_active' => false]);
        $other->categories()->sync(ProductCategory::query()->where('key', 'balls')->pluck('id'));
        $footwear = ProductCategory::query()->where('key', 'footwear')->value('id');

        $this->getJson('/api/stores')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Wally Shop')
            ->assertJsonPath('data.0.venue.name', 'Complejo Wally Sur')
            ->assertJsonPath('data.0.categories.0.key', 'balls')
            ->assertJsonPath('data.0.products_count', 2)
            ->assertJsonPath('data.0.offers_count', 1);

        $this->getJson('/api/stores?category_id='.$footwear)->assertOk()->assertJsonCount(0, 'data');

        $categories = collect($this->getJson('/api/product-categories')->assertOk()->json('data'))->keyBy('key');
        $this->assertSame(1, $categories['balls']['stores_count']);
        $this->assertSame(0, $categories['footwear']['stores_count']);
    }

    public function test_products_show_the_discounted_price_and_the_units_left_after_pending_orders(): void
    {
        $this->ball->update(['is_active' => true]);
        $this->product(['product_category_id' => $this->ball->product_category_id, 'name' => 'Oculto', 'price' => 1, 'is_active' => false], 1);

        Sanctum::actingAs(User::factory()->create());
        $this->postJson('/api/store-orders', $this->payload([[$this->ball, 2]]))->assertCreated();

        $products = collect($this->getJson("/api/stores/{$this->store->id}/products")->assertOk()->json('data'))->keyBy('name');

        $this->assertCount(2, $products);
        $this->assertEquals(108, $products['Pelota de wally']['final_price']);
        // 5 in stock, 2 held by the pending order.
        $this->assertSame(3, $products['Pelota de wally']['available']);
        $this->assertSame(10, $products['Isotónica']['available']);
    }

    public function test_an_order_holds_its_units_and_nobody_can_buy_more_than_what_is_left(): void
    {
        $buyer = User::factory()->create();
        Sanctum::actingAs($buyer);

        $response = $this->postJson('/api/store-orders', $this->payload([[$this->ball, 3], [$this->drink, 2], [$this->ball, 1]]))
            ->assertCreated()
            ->assertJsonPath('data.status', StoreOrder::STATUS_PENDING_PAYMENT)
            ->assertJsonCount(2, 'data.items')
            ->assertJsonPath('data.subtotal', 504)
            ->assertJsonPath('data.discount_amount', 48)
            ->assertJsonPath('data.total', 456);
        $this->assertStringStartsWith('T', $response->json('data.code'));

        // Holding does not take units out of the stock yet.
        $this->assertSame(5, $this->ball->fresh()->stock);

        Sanctum::actingAs(User::factory()->create());
        $this->postJson('/api/store-orders', $this->payload([[$this->ball, 2]]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['items.0.quantity' => 'Solo quedan 1 unidades de Pelota de wally.']);

        // Once the payment window ends the units are free again.
        $this->travel(StoreOrder::PAYMENT_WINDOW_MINUTES + 1)->minutes();
        $this->postJson('/api/store-orders', $this->payload([[$this->ball, 5]]))->assertCreated();
        $this->postJson('/api/store-orders', $this->payload([[$this->ball, 1]]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['items.0.quantity' => 'Pelota de wally está agotado.']);
    }

    public function test_paying_takes_the_units_out_of_the_stock_and_records_the_sale(): void
    {
        $buyer = User::factory()->create();
        Sanctum::actingAs($buyer);
        $orderId = $this->postJson('/api/store-orders', $this->payload([[$this->ball, 2], [$this->drink, 3]]))->json('data.id');

        $this->postJson("/api/store-orders/{$orderId}/pay")
            ->assertOk()
            ->assertJsonPath('data.status', StoreOrder::STATUS_PAID);

        $this->assertSame(3, $this->ball->fresh()->stock);
        $this->assertSame(7, $this->drink->fresh()->stock);
        $this->assertDatabaseHas('stock_movements', [
            'product_id' => $this->ball->id,
            'store_order_id' => $orderId,
            'type' => StockMovement::TYPE_SALE,
            'quantity' => -2,
            'stock_after' => 3,
        ]);
        // Paid units are no longer held: available matches the stock.
        $this->assertSame(3, $this->ball->fresh()->availableQuantity());

        // Paying twice does not take units out again.
        $this->postJson("/api/store-orders/{$orderId}/pay")->assertOk();
        $this->assertSame(3, $this->ball->fresh()->stock);
    }

    public function test_an_expired_or_foreign_order_cannot_be_paid(): void
    {
        $buyer = User::factory()->create();
        Sanctum::actingAs($buyer);
        $orderId = $this->postJson('/api/store-orders', $this->payload([[$this->ball, 1]]))->json('data.id');

        Sanctum::actingAs(User::factory()->create());
        $this->postJson("/api/store-orders/{$orderId}/pay")->assertForbidden();
        $this->postJson("/api/store-orders/{$orderId}/cancel")->assertForbidden();

        Sanctum::actingAs($buyer);
        $this->travel(StoreOrder::PAYMENT_WINDOW_MINUTES + 1)->minutes();
        $this->postJson("/api/store-orders/{$orderId}/pay")
            ->assertUnprocessable()
            ->assertJsonValidationErrors('order');
        $this->assertSame(5, $this->ball->fresh()->stock);
    }

    public function test_products_of_another_store_or_inactive_ones_are_rejected(): void
    {
        $otherStore = Store::query()->create(['court_id' => $this->store->court_id, 'name' => 'Otra']);
        $foreign = $otherStore->products()->create(['product_category_id' => $this->ball->product_category_id, 'name' => 'Ajeno', 'price' => 5, 'stock' => 3]);
        $this->drink->update(['is_active' => false]);

        Sanctum::actingAs(User::factory()->create());
        $this->postJson('/api/store-orders', $this->payload([[$foreign, 1]]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('items.0.product_id');
        $this->postJson('/api/store-orders', $this->payload([[$this->ball, 1], [$this->drink, 1]]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('items.1.product_id');

        $this->store->update(['is_active' => false]);
        $this->postJson('/api/store-orders', $this->payload([[$this->ball, 1]]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('store_id');
        $this->getJson("/api/stores/{$this->store->id}/products")->assertNotFound();
    }

    public function test_cancelling_releases_the_units_and_my_orders_hide_it(): void
    {
        $buyer = User::factory()->create();
        Sanctum::actingAs($buyer);
        $cancelled = $this->postJson('/api/store-orders', $this->payload([[$this->ball, 4]]))->json('data.id');
        $paid = $this->postJson('/api/store-orders', $this->payload([[$this->drink, 1]]))->json('data.id');
        $this->postJson("/api/store-orders/{$paid}/pay")->assertOk();

        $this->postJson("/api/store-orders/{$cancelled}/cancel")->assertNoContent();
        $this->assertSame(5, $this->ball->fresh()->availableQuantity());
        $this->postJson("/api/store-orders/{$paid}/cancel")->assertUnprocessable();

        // Cancelled by the venue: stays in the list with the reason.
        $venueCancelled = StoreOrder::query()->create([
            'code' => 'TVENUE01', 'store_id' => $this->store->id, 'user_id' => $buyer->id,
            'subtotal' => 12, 'total' => 12, 'status' => StoreOrder::STATUS_CANCELLED,
            'cancelled_at' => now(), 'cancelled_by' => User::factory()->create()->id, 'cancellation_reason' => 'Producto dañado',
        ]);

        $this->getJson('/api/store-orders')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.id', $venueCancelled->id)
            ->assertJsonPath('data.0.cancellation_reason', 'Producto dañado')
            ->assertJsonPath('data.1.id', $paid)
            ->assertJsonPath('data.1.items.0.name', 'Isotónica')
            ->assertJsonPath('data.1.store.name', 'Wally Shop');
    }
}
