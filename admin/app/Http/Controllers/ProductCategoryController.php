<?php

namespace App\Http\Controllers;

use App\DataTables\ProductCategoryDataTable;
use App\Http\Requests\ProductCategories\ProductCategoryRequest;
use App\Models\ProductCategory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * Catalog of product categories (product_categories), offered when creating stores and products.
 */
class ProductCategoryController extends Controller
{
    public function index(ProductCategoryDataTable $dataTable): mixed
    {
        return $dataTable->render('product-categories.index');
    }

    public function create(): View
    {
        return view('product-categories.create', ['category' => new ProductCategory]);
    }

    public function store(ProductCategoryRequest $request): RedirectResponse
    {
        $category = ProductCategory::query()->create($request->validated());

        return redirect()->route('product-categories.index')->with('success', "Categoría {$category->name} creada.");
    }

    public function edit(ProductCategory $productCategory): View
    {
        return view('product-categories.edit', ['category' => $productCategory]);
    }

    public function update(ProductCategoryRequest $request, ProductCategory $productCategory): RedirectResponse
    {
        $productCategory->update($request->validated());

        return redirect()->route('product-categories.index')->with('success', "Categoría {$productCategory->name} actualizada.");
    }

    public function destroy(ProductCategory $productCategory): JsonResponse
    {
        // products.product_category_id is restrictOnDelete: the products must be moved first.
        $products = $productCategory->products()->count();
        if ($products > 0) {
            return response()->json([
                'message' => "No se puede eliminar: {$products} producto(s) usan esta categoría. Cámbialos de categoría primero.",
            ], 422);
        }

        $productCategory->delete();

        return response()->json(['message' => "Categoría {$productCategory->name} eliminada."]);
    }
}
