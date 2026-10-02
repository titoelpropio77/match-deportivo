<?php

namespace App\Http\Controllers;

use App\DataTables\ProductDataTable;
use App\DataTables\StockMovementDataTable;
use App\DataTables\StoreDataTable;
use App\Http\Requests\Stores\StoreRequest;
use App\Models\Court;
use App\Models\CourtPhoto;
use App\Models\ProductCategory;
use App\Models\StockMovement;
use App\Models\Store;
use App\Models\StoreOrder;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Stores of the sports centers. Partners and admins open them; managers run their products,
 * stock and sales.
 */
class StoreController extends Controller
{
    public function index(StoreDataTable $dataTable): mixed
    {
        return $dataTable->render('stores.index');
    }

    public function create(Request $request): View
    {
        return view('stores.create', [
            'store' => new Store(['is_active' => true, 'court_id' => $request->integer('court_id') ?: null]),
            ...$this->formOptions($request->user()),
        ]);
    }

    public function store(StoreRequest $request): RedirectResponse
    {
        $store = DB::transaction(function () use ($request): Store {
            $store = Store::query()->create([...$request->storeData(), 'created_by' => $request->user()->id]);
            $store->categories()->sync($request->validated('categories'));
            if ($request->hasFile('cover')) {
                $store->update(['cover_path' => $request->file('cover')->store("stores/{$store->id}", CourtPhoto::DISK)]);
            }

            return $store;
        });

        return redirect()->route('stores.show', $store)
            ->with('success', "Tienda {$store->name} creada. Ahora carga sus productos.");
    }

    /**
     * Products tab: the store summary and its products table.
     */
    public function show(Store $store, ProductDataTable $dataTable): mixed
    {
        Gate::authorize('manage', $store->court);

        return $dataTable->with('store', $store)->render('stores.show', [
            'store' => $this->withSummary($store),
            'movementTypes' => collect(StockMovement::TYPES)->only(StockMovement::MANUAL_TYPES),
        ]);
    }

    /**
     * Stock movements tab: every change of stock of the store's products.
     */
    public function movements(Store $store, StockMovementDataTable $dataTable): mixed
    {
        Gate::authorize('manage', $store->court);

        return $dataTable->with('store', $store)->render('stores.movements', [
            'store' => $this->withSummary($store),
            'types' => StockMovement::TYPES,
        ]);
    }

    public function edit(Request $request, Store $store): View
    {
        Gate::authorize('manage', $store->court);

        $store->load('categories');

        return view('stores.edit', ['store' => $store, ...$this->formOptions($request->user())]);
    }

    public function update(StoreRequest $request, Store $store): RedirectResponse
    {
        $previousCover = $store->cover_path;
        $attributes = $request->storeData();
        if ($request->hasFile('cover')) {
            $attributes['cover_path'] = $request->file('cover')->store("stores/{$store->id}", CourtPhoto::DISK);
        } elseif ($request->boolean('remove_cover')) {
            $attributes['cover_path'] = null;
        }

        DB::transaction(function () use ($store, $attributes, $request): void {
            $store->update($attributes);
            $store->categories()->sync($request->validated('categories'));
        });

        if ($previousCover !== $store->cover_path) {
            (new Store(['cover_path' => $previousCover]))->deleteCover();
        }

        return redirect()->route('stores.show', $store)->with('success', "Tienda {$store->name} actualizada.");
    }

    /**
     * Only stores without sales: the sales history must survive (deactivate it instead).
     */
    public function destroy(Store $store): JsonResponse
    {
        Gate::authorize('manage', $store->court);

        if ($store->orders()->exists()) {
            return response()->json([
                'message' => 'No se puede eliminar: la tienda tiene ventas registradas. Desactívala para ocultarla de la app.',
            ], 422);
        }

        $photos = $store->products()->with('photos')->get()->flatMap->photos;
        $store->delete();
        $store->deleteCover();
        $photos->each->deleteFile();

        return response()->json(['message' => "Tienda {$store->name} eliminada."]);
    }

    /**
     * Counters shown on the store header (products, units, low stock, sales of the month...).
     */
    private function withSummary(Store $store): Store
    {
        $store->load(['court', 'categories', 'createdBy'])
            ->loadCount([
                'products',
                'products as active_products_count' => fn ($products) => $products->where('is_active', true),
                'products as low_stock_count' => fn ($products) => $products->lowStock(),
                'orders as to_deliver_count' => fn ($orders) => $orders->where('status', StoreOrder::STATUS_PAID)->whereNull('delivered_at'),
            ])
            ->loadSum('products as units_in_stock', 'stock');

        $store->setAttribute('month_sales', (float) $store->orders()
            ->collected()
            ->where('paid_at', '>=', now()->startOfMonth())
            ->sum('total'));

        return $store;
    }

    /**
     * @return array<string, mixed>
     */
    private function formOptions(User $user): array
    {
        return [
            'courts' => Court::query()->visibleTo($user)->orderBy('name')->get(['id', 'name']),
            'categories' => ProductCategory::query()->orderBy('name')->get(),
        ];
    }
}
