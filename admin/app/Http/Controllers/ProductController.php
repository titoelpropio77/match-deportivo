<?php

namespace App\Http\Controllers;

use App\Http\Requests\Stores\ProductRequest;
use App\Http\Requests\Stores\StockMovementRequest;
use App\Models\CourtPhoto;
use App\Models\Product;
use App\Models\ProductPhoto;
use App\Models\StockMovement;
use App\Models\Store;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Products of a store: data, photo gallery and stock movements.
 */
class ProductController extends Controller
{
    public function create(Store $store): View
    {
        Gate::authorize('manage', $store->court);

        return view('products.create', [
            'store' => $store->load('categories'),
            'product' => new Product(['is_active' => true, 'discount_percent' => 0, 'stock' => 0]),
        ]);
    }

    public function store(ProductRequest $request, Store $store): RedirectResponse
    {
        $product = DB::transaction(function () use ($request, $store): Product {
            $product = $store->products()->create([...$request->productData(), 'stock' => 0]);
            $initial = (int) $request->validated('stock');
            if ($initial > 0) {
                $product->moveStock($initial, StockMovement::TYPE_INITIAL, $request->user()->id, reason: 'Stock inicial');
            }
            $this->storePhotos($product, $request->file('photos', []));

            return $product;
        });

        return redirect()->route('stores.show', $store)->with('success', "Producto {$product->name} creado.");
    }

    public function edit(Store $store, Product $product): View
    {
        Gate::authorize('manage', $store->court);

        return view('products.edit', [
            'store' => $store->load('categories'),
            'product' => $product->load(['photos', 'category']),
            'held' => $product->heldQuantity(),
        ]);
    }

    public function update(ProductRequest $request, Store $store, Product $product): RedirectResponse
    {
        $validated = $request->validated();
        $removed = $product->photos()->whereIn('id', $validated['remove_photos'] ?? [])->get();

        DB::transaction(function () use ($request, $product, $removed): void {
            $product->update($request->productData());
            ProductPhoto::query()->whereKey($removed->modelKeys())->delete();
            $this->storePhotos($product, $request->file('photos', []));
        });
        $removed->each->deleteFile();

        return redirect()->route('stores.show', $store)->with('success', "Producto {$product->name} actualizado.");
    }

    /**
     * Sales keep a copy of the product, so deleting it does not change history. Units held by an
     * unpaid app order block the deletion until the order is paid or expires.
     */
    public function destroy(Store $store, Product $product): JsonResponse
    {
        Gate::authorize('manage', $store->court);

        if ($product->heldQuantity() > 0) {
            return response()->json([
                'message' => 'No se puede eliminar: hay pedidos de la app esperando su pago con este producto. Desactívalo e inténtalo en unos minutos.',
            ], 422);
        }

        $photos = $product->photos()->get();
        $product->delete();
        $photos->each->deleteFile();

        return response()->json(['message' => "Producto {$product->name} eliminado."]);
    }

    /**
     * Restock (+), loss (−) or physical count. The stock can never drop below the units that
     * pending app orders hold, so those orders can always be paid.
     */
    public function moveStock(StockMovementRequest $request, Store $store, Product $product): RedirectResponse
    {
        $validated = $request->validated();

        $movement = DB::transaction(function () use ($validated, $product, $request): StockMovement {
            $product = Product::query()->lockForUpdate()->findOrFail($product->id);
            $quantity = (int) $validated['quantity'];
            $delta = match ($validated['type']) {
                StockMovement::TYPE_RESTOCK => $quantity,
                StockMovement::TYPE_LOSS => -$quantity,
                StockMovement::TYPE_ADJUSTMENT => $quantity - $product->stock,
            };

            if ($delta === 0) {
                $this->failStock('quantity', "El stock de {$product->name} ya es {$product->stock}.");
            }

            $held = $product->heldQuantity();
            if ($product->stock + $delta < $held) {
                $this->failStock('quantity', $held > 0
                    ? "No puedes dejar menos de {$held} unidades: están apartadas por pedidos de la app pendientes de pago."
                    : "Solo hay {$product->stock} unidades de {$product->name}.");
            }

            return $product->moveStock($delta, $validated['type'], $request->user()->id, reason: $validated['reason'] ?? null);
        });

        return redirect()->route('stores.show', $store)->with('success', sprintf(
            '%s de %s: %+d unidades. Stock actual: %d.',
            $movement->typeLabel(),
            $product->name,
            $movement->quantity,
            $movement->stock_after,
        ));
    }

    /**
     * @param  array<int, UploadedFile>  $files
     */
    private function storePhotos(Product $product, array $files): void
    {
        $order = (int) $product->photos()->max('order');

        foreach ($files as $file) {
            $product->photos()->create([
                'path' => $file->store("products/{$product->id}", CourtPhoto::DISK),
                'order' => ++$order,
            ]);
        }
    }

    /**
     * Error of the stock modal; product_id lets the page reopen it for the same product.
     */
    private function failStock(string $key, string $message): never
    {
        throw ValidationException::withMessages([$key => $message])->errorBag('stock');
    }
}
