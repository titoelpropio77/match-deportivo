<?php

namespace App\Services;

use App\Models\Product;
use App\Models\StockMovement;
use App\Models\Store;
use App\Models\StoreOrder;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Store purchases from the app. An order holds its units as pending payment (they are not taken
 * out of the stock yet, but nobody else can buy them) until the simulated QR is paid; then the
 * stock goes down and each line is recorded as a sale in stock_movements.
 */
class StoreOrderService
{
    /**
     * @param  array{store_id: int, items: list<array{product_id: int, quantity: int}>, notes?: string|null}  $data
     */
    public function place(User $user, array $data): StoreOrder
    {
        return DB::transaction(function () use ($user, $data): StoreOrder {
            $store = Store::query()->findOrFail($data['store_id']);
            if (! $store->is_active) {
                $this->fail('store_id', "{$store->name} no está recibiendo pedidos.");
            }

            // The same product twice is one line.
            $quantities = collect($data['items'])
                ->groupBy('product_id')
                ->map(fn (Collection $lines) => (int) $lines->sum('quantity'));

            $products = $this->lockProducts($quantities->keys());

            $lines = [];
            foreach ($quantities as $productId => $quantity) {
                $index = collect($data['items'])->search(fn (array $line) => (int) $line['product_id'] === (int) $productId);
                $product = $products->get($productId);

                if ($product === null || $product->store_id !== $store->id || ! $product->is_active) {
                    $this->fail("items.{$index}.product_id", 'Ese producto ya no está a la venta en esta tienda.');
                }

                $available = $product->availableQuantity();
                if ($quantity > $available) {
                    $this->fail("items.{$index}.quantity", $available === 0
                        ? "{$product->name} está agotado."
                        : "Solo quedan {$available} unidades de {$product->name}.");
                }

                $lines[] = $this->line($product, $quantity);
            }

            $subtotal = round(array_sum(array_map(fn (array $line) => $line['list_price'] * $line['quantity'], $lines)), 2);
            $total = round(array_sum(array_column($lines, 'amount')), 2);

            $order = StoreOrder::query()->create([
                'code' => $this->newCode(),
                'store_id' => $store->id,
                'user_id' => $user->id,
                'subtotal' => $subtotal,
                'discount_amount' => round($subtotal - $total, 2),
                'total' => $total,
                'status' => StoreOrder::STATUS_PENDING_PAYMENT,
                'source' => 'app',
                'notes' => $data['notes'] ?? null,
            ]);
            $order->items()->createMany($lines);

            return $order;
        });
    }

    /**
     * Simulated QR payment: the held units leave the stock.
     * TODO: replace with the bank QR webhook once the payment provider is connected.
     */
    public function pay(StoreOrder $order): StoreOrder
    {
        return DB::transaction(function () use ($order): StoreOrder {
            $order = StoreOrder::query()->with('items')->lockForUpdate()->findOrFail($order->id);

            if ($order->status === StoreOrder::STATUS_PAID) {
                return $order;
            }

            if ($order->status !== StoreOrder::STATUS_PENDING_PAYMENT || $order->paymentExpired()) {
                $this->fail('order', 'El pedido expiró o fue cancelado. Vuelve a armar tu carrito.');
            }

            $products = $this->lockProducts($order->items->pluck('product_id')->filter());
            foreach ($order->items as $item) {
                $product = $products->get($item->product_id);
                // The order's own hold covers it; the panel never lets the stock drop below what is held.
                if ($product === null || $product->stock < $item->quantity) {
                    $this->fail('order', "{$item->name} ya no tiene stock suficiente.");
                }
                $product->moveStock(-$item->quantity, StockMovement::TYPE_SALE, $order->user_id, $order);
            }

            $order->update([
                'status' => StoreOrder::STATUS_PAID,
                'payment_method' => 'qr',
                'paid_at' => now(),
            ]);

            return $order;
        });
    }

    /**
     * Releases the units of an unpaid order (the user left the payment screen).
     */
    public function cancel(StoreOrder $order, User $user): void
    {
        if ($order->status !== StoreOrder::STATUS_PENDING_PAYMENT) {
            $this->fail('order', 'Solo se pueden cancelar pedidos pendientes de pago.');
        }

        $order->update([
            'status' => StoreOrder::STATUS_CANCELLED,
            'cancelled_at' => now(),
            'cancelled_by' => $user->id,
        ]);
    }

    /**
     * Locked in id order so concurrent orders of the same products do not deadlock.
     *
     * @param  Collection<int, int|string>  $ids
     * @return Collection<int, Product>
     */
    private function lockProducts(Collection $ids): Collection
    {
        return Product::query()
            ->whereKey($ids->all())
            ->orderBy('id')
            ->lockForUpdate()
            ->get()
            ->keyBy('id');
    }

    /**
     * @return array{product_id: int, name: string, list_price: float, discount_percent: int, unit_price: float, quantity: int, amount: float}
     */
    private function line(Product $product, int $quantity): array
    {
        $unitPrice = $product->finalPrice();

        return [
            'product_id' => $product->id,
            'name' => $product->name,
            'list_price' => (float) $product->price,
            'discount_percent' => $product->discount_percent,
            'unit_price' => $unitPrice,
            'quantity' => $quantity,
            'amount' => round($unitPrice * $quantity, 2),
        ];
    }

    private function newCode(): string
    {
        do {
            $code = 'T'.Str::upper(Str::random(7));
        } while (StoreOrder::query()->where('code', $code)->exists());

        return $code;
    }

    private function fail(string $key, string $message): never
    {
        throw ValidationException::withMessages([$key => $message]);
    }
}
