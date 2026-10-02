<?php

namespace App\Http\Controllers;

use App\DataTables\StoreOrderDataTable;
use App\Http\Requests\StoreOrders\CancelStoreOrderRequest;
use App\Http\Requests\StoreOrders\StoreOrderRequest;
use App\Models\CourtReservation;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\Store;
use App\Models\StoreOrder;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Sales of the stores the user can see: app purchases (picked up at the store) and counter sales.
 */
class StoreOrderController extends Controller
{
    public function index(Request $request, StoreOrderDataTable $dataTable): mixed
    {
        return $dataTable->render('store-orders.index', [
            'stores' => Store::query()->visibleTo($request->user())->with('court:id,name')->orderBy('name')->get(),
            'statuses' => collect(StoreOrder::DISPLAY_STATUSES)->map(fn (array $status) => $status[0]),
            'filters' => $request->only(['store_id', 'status', 'source', 'date_from', 'date_to']),
        ]);
    }

    /**
     * Counter sale: products of every visible store with the units that can be sold now.
     */
    public function create(Request $request): View
    {
        $stores = Store::query()
            ->visibleTo($request->user())
            ->with(['court:id,name', 'products' => fn ($products) => $products->where('is_active', true)->with('category:id,name')->orderBy('name')])
            ->orderBy('name')
            ->get();

        $catalog = $stores->mapWithKeys(fn (Store $store) => [$store->id => $store->products->map(fn (Product $product) => [
            'id' => $product->id,
            'name' => $product->name,
            'sku' => $product->sku,
            'category' => $product->category?->name,
            'list_price' => (float) $product->price,
            'discount_percent' => $product->discount_percent,
            'price' => $product->finalPrice(),
            'available' => $product->availableQuantity(),
        ])->values()]);

        return view('store-orders.create', [
            'stores' => $stores,
            'catalog' => $catalog,
            'paymentMethods' => CourtReservation::PAYMENT_METHODS,
            'selectedStore' => old('store_id', $request->integer('store_id') ?: ($stores->count() === 1 ? $stores->first()->id : null)),
        ]);
    }

    /**
     * Paid at once: the units leave the stock and each line is recorded as a sale.
     */
    public function store(StoreOrderRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $store = Store::query()->with('court')->findOrFail($validated['store_id']);
        Gate::authorize('manage', $store->court);

        $order = DB::transaction(function () use ($validated, $store, $request): StoreOrder {
            $quantities = collect($validated['items'])
                ->groupBy('product_id')
                ->map(fn ($lines) => (int) $lines->sum('quantity'));
            $products = Product::query()->whereKey($quantities->keys()->all())->orderBy('id')->lockForUpdate()->get()->keyBy('id');

            $lines = [];
            foreach ($quantities as $productId => $quantity) {
                $index = collect($validated['items'])->search(fn (array $line) => (int) $line['product_id'] === (int) $productId);
                $product = $products->get($productId);

                if ($product === null || $product->store_id !== $store->id || ! $product->is_active) {
                    throw ValidationException::withMessages(["items.{$index}.product_id" => 'Ese producto no está a la venta en esta tienda.']);
                }
                $available = $product->availableQuantity();
                if ($quantity > $available) {
                    throw ValidationException::withMessages(["items.{$index}.quantity" => $available === 0
                        ? "{$product->name} está agotado."
                        : "Solo quedan {$available} unidades de {$product->name}."]);
                }

                $unitPrice = $product->finalPrice();
                $lines[] = [$product, [
                    'product_id' => $product->id,
                    'name' => $product->name,
                    'list_price' => (float) $product->price,
                    'discount_percent' => $product->discount_percent,
                    'unit_price' => $unitPrice,
                    'quantity' => $quantity,
                    'amount' => round($unitPrice * $quantity, 2),
                ]];
            }

            $subtotal = round(collect($lines)->sum(fn (array $line) => $line[1]['list_price'] * $line[1]['quantity']), 2);
            $total = round(collect($lines)->sum(fn (array $line) => $line[1]['amount']), 2);
            $user = isset($validated['user_email']) ? User::query()->where('email', $validated['user_email'])->first() : null;
            $now = now();

            $order = StoreOrder::query()->create([
                'code' => $this->newCode(),
                'store_id' => $store->id,
                'user_id' => $user?->id,
                'customer_name' => $validated['customer_name'] ?? null,
                'customer_phone' => $validated['customer_phone'] ?? null,
                'subtotal' => $subtotal,
                'discount_amount' => round($subtotal - $total, 2),
                'total' => $total,
                'status' => StoreOrder::STATUS_PAID,
                'source' => 'admin',
                'payment_method' => $validated['payment_method'],
                'paid_at' => $now,
                'delivered_at' => $validated['delivered'] ? $now : null,
                'delivered_by' => $validated['delivered'] ? $request->user()->id : null,
                'created_by' => $request->user()->id,
                'notes' => $validated['notes'] ?? null,
            ]);

            foreach ($lines as [$product, $line]) {
                $order->items()->create($line);
                $product->moveStock(-$line['quantity'], StockMovement::TYPE_SALE, $request->user()->id, $order);
            }

            return $order;
        });

        return redirect()->route('store-orders.show', $order)
            ->with('success', "Venta {$order->code} registrada por Bs ".number_format((float) $order->total, 2).'.');
    }

    public function show(StoreOrder $order): View
    {
        $this->authorizeOrder($order);

        $order->load(['store.court', 'items.product.photos', 'user', 'createdBy', 'cancelledBy', 'deliveredBy', 'stockMovements']);

        return view('store-orders.show', [
            'order' => $order,
            'paymentMethods' => CourtReservation::PAYMENT_METHODS,
        ]);
    }

    public function deliver(Request $request, StoreOrder $order): RedirectResponse
    {
        $this->authorizeOrder($order);

        if (! $order->canBeDelivered()) {
            return back()->with('error', 'Esta venta no está pendiente de entrega.');
        }

        $order->update(['delivered_at' => now(), 'delivered_by' => $request->user()->id]);

        return redirect()->route('store-orders.show', $order)->with('success', "Venta {$order->code} entregada al cliente.");
    }

    /**
     * A pending app order just releases its held units; a paid one optionally puts them back in stock.
     */
    public function cancel(CancelStoreOrderRequest $request, StoreOrder $order): RedirectResponse
    {
        $this->authorizeOrder($order);

        $validated = $request->validated();

        $cancelled = DB::transaction(function () use ($order, $validated, $request): bool {
            $order = StoreOrder::query()->with('items')->lockForUpdate()->findOrFail($order->id);
            if (! $order->canBeCancelled()) {
                return false;
            }

            if ($order->status === StoreOrder::STATUS_PAID && $validated['restock']) {
                $products = Product::query()->whereKey($order->items->pluck('product_id')->filter()->all())->orderBy('id')->lockForUpdate()->get()->keyBy('id');
                foreach ($order->items as $item) {
                    $products->get($item->product_id)?->moveStock($item->quantity, StockMovement::TYPE_RETURN, $request->user()->id, $order, 'Venta anulada');
                }
            }

            $order->update([
                'status' => StoreOrder::STATUS_CANCELLED,
                'cancelled_at' => now(),
                'cancelled_by' => $request->user()->id,
                'cancellation_reason' => $validated['cancellation_reason'],
            ]);

            return true;
        });

        if (! $cancelled) {
            return back()->with('error', 'Esta venta ya no se puede anular.');
        }

        $order->refresh();
        $message = "Venta {$order->code} anulada.";
        if ($order->paid_at !== null) {
            $message .= ' Recuerda devolver Bs '.number_format((float) $order->total, 2).' al cliente.';
        }

        return redirect()->route('store-orders.show', $order)->with('success', $message);
    }

    public function refund(StoreOrder $order): RedirectResponse
    {
        $this->authorizeOrder($order);

        if (! $order->canBeRefunded()) {
            return back()->with('error', 'Esta venta no tiene una devolución pendiente.');
        }

        $order->update(['refunded_at' => now()]);

        return redirect()->route('store-orders.show', $order)->with('success', "Devolución de la venta {$order->code} registrada.");
    }

    private function authorizeOrder(StoreOrder $order): void
    {
        Gate::authorize('manage', $order->store()->with('court')->firstOrFail()->court);
    }

    private function newCode(): string
    {
        do {
            $code = 'T'.Str::upper(Str::random(7));
        } while (StoreOrder::query()->where('code', $code)->exists());

        return $code;
    }
}
