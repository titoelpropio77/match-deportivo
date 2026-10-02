<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProductCategoryResource;
use App\Http\Resources\ProductResource;
use App\Http\Resources\StoreResource;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Store;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Stores of the sports centers and their products. Only active stores and products are listed.
 */
class StoreController extends Controller
{
    /**
     * Categories of the catalog with how many active stores sell them (the app hides empty ones).
     */
    public function categories(): JsonResponse
    {
        $categories = ProductCategory::query()
            ->withCount(['stores' => fn (Builder $stores) => $stores->where('is_active', true)])
            ->orderBy('name')
            ->get();

        return response()->json(['data' => ProductCategoryResource::collection($categories)]);
    }

    /**
     * Filters: product category, city, sports center and a search term (store name).
     */
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'category_id' => ['nullable', 'integer', 'exists:product_categories,id'],
            'city_id' => ['nullable', 'integer', 'exists:cities,id'],
            'court_id' => ['nullable', 'integer', 'exists:courts,id'],
            'search' => ['nullable', 'string', 'max:100'],
        ]);

        $stores = Store::query()
            ->active()
            ->with(['court.city', 'court.photos', 'categories'])
            ->withCount([
                'products' => fn (Builder $products) => $products->where('is_active', true),
                'products as offers_count' => fn (Builder $products) => $products->where('is_active', true)->where('discount_percent', '>', 0),
            ])
            ->when($validated['category_id'] ?? null, fn (Builder $query, $categoryId) => $query
                ->whereHas('categories', fn (Builder $categories) => $categories->whereKey($categoryId)))
            ->when($validated['court_id'] ?? null, fn (Builder $query, $courtId) => $query->where('court_id', $courtId))
            ->when($validated['city_id'] ?? null, fn (Builder $query, $cityId) => $query
                ->whereHas('court', fn (Builder $court) => $court->where('city_id', $cityId)))
            ->when($validated['search'] ?? null, fn (Builder $query, $search) => $query
                ->whereRaw('lower(name) like ?', ['%'.Str::lower($search).'%']))
            ->orderBy('name')
            ->get();

        return response()->json(['data' => StoreResource::collection($stores)]);
    }

    public function show(Store $store): JsonResponse
    {
        abort_unless($store->is_active, 404);

        $store->load(['court.city', 'court.photos', 'categories'])
            ->loadCount(['products' => fn (Builder $products) => $products->where('is_active', true)]);

        return response()->json(['data' => new StoreResource($store)]);
    }

    /**
     * Active products of a store with their available units; discounted first within each category filter.
     */
    public function products(Request $request, Store $store): JsonResponse
    {
        abort_unless($store->is_active, 404);

        $validated = $request->validate([
            'category_id' => ['nullable', 'integer', 'exists:product_categories,id'],
            'search' => ['nullable', 'string', 'max:100'],
        ]);

        $products = $store->products()
            ->active()
            ->with(['category', 'photos'])
            ->when($validated['category_id'] ?? null, fn (Builder $query, $categoryId) => $query->where('product_category_id', $categoryId))
            ->when($validated['search'] ?? null, fn (Builder $query, $search) => $query
                ->whereRaw('lower(name) like ?', ['%'.Str::lower($search).'%']))
            ->orderBy('name')
            ->get();
        Product::loadHeldQuantities($products);

        return response()->json(['data' => ProductResource::collection($products)]);
    }
}
