<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\StoreOrderResource;
use App\Models\StoreOrder;
use App\Services\StoreOrderService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Store purchases of the signed-in user, paid with the simulated QR and picked up at the store.
 */
class StoreOrderController extends Controller
{
    private const MAX_LINES = 30;

    private const MAX_QUANTITY = 50;

    public function store(Request $request, StoreOrderService $orders): JsonResponse
    {
        $validated = $request->validate([
            'store_id' => ['required', 'integer', 'exists:stores,id'],
            'items' => ['required', 'array', 'min:1', 'max:'.self::MAX_LINES],
            'items.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'items.*.quantity' => ['required', 'integer', 'between:1,'.self::MAX_QUANTITY],
            'notes' => ['nullable', 'string', 'max:500'],
        ], [
            'items.required' => 'Tu carrito está vacío.',
            'items.*.quantity.between' => 'Puedes llevar entre 1 y '.self::MAX_QUANTITY.' unidades de cada producto.',
        ]);

        $order = $orders->place($request->user(), $validated);

        return response()->json(['data' => $this->resource($order)], 201);
    }

    /**
     * Newest first. Expired holds and the ones the user cancelled are left out; venue
     * cancellations stay so the user learns why.
     */
    public function mine(Request $request): JsonResponse
    {
        $orders = StoreOrder::query()
            ->with(['store.court.city', 'items.product.photos'])
            ->where('user_id', $request->user()->id)
            ->where(fn (Builder $query) => $query
                ->where('status', StoreOrder::STATUS_PAID)
                ->orWhere(fn (Builder $pending) => $pending->holdingStock())
                ->orWhere(fn (Builder $cancelled) => $cancelled
                    ->where('status', StoreOrder::STATUS_CANCELLED)
                    ->whereNotNull('cancelled_by')
                    ->whereColumn('cancelled_by', '!=', 'user_id')))
            ->latest()
            ->latest('id')
            ->get();

        return response()->json(['data' => StoreOrderResource::collection($orders)]);
    }

    public function pay(Request $request, StoreOrder $order, StoreOrderService $orders): JsonResponse
    {
        abort_unless($order->user_id === $request->user()->id, 403);

        return response()->json(['data' => $this->resource($orders->pay($order))]);
    }

    public function cancel(Request $request, StoreOrder $order, StoreOrderService $orders): JsonResponse
    {
        abort_unless($order->user_id === $request->user()->id, 403);

        $orders->cancel($order, $request->user());

        return response()->json(null, 204);
    }

    private function resource(StoreOrder $order): StoreOrderResource
    {
        return new StoreOrderResource($order->load(['store.court.city', 'items.product.photos']));
    }
}
