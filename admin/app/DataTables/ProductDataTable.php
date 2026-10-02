<?php

namespace App\DataTables;

use App\Models\Product;
use App\Models\Store;
use Illuminate\Database\Eloquent\Builder;
use Yajra\DataTables\EloquentDataTable;
use Yajra\DataTables\Html\Column;

/**
 * Products of one store (passed with ->with('store', $store)) with prices and stock.
 */
class ProductDataTable extends BaseDataTable
{
    public function tableId(): string
    {
        return 'products-table';
    }

    public function dataTable(Builder $query): EloquentDataTable
    {
        return (new EloquentDataTable($query))
            ->editColumn('name', fn (Product $product) => view('products.partials.name', ['product' => $product])->render())
            ->filterColumn('name', function (Builder $query, string $keyword): void {
                $query->where(fn (Builder $product) => $product
                    ->where('products.name', 'ilike', "%{$keyword}%")
                    ->orWhere('products.sku', 'ilike', "%{$keyword}%"));
            })
            ->addColumn('category', fn (Product $product) => e($product->category->name))
            ->filterColumn('category', function (Builder $query, string $keyword): void {
                $query->whereHas('category', fn (Builder $category) => $category->where('name', 'ilike', "%{$keyword}%"));
            })
            ->editColumn('price', fn (Product $product) => view('products.partials.price', ['product' => $product])->render())
            ->editColumn('stock', fn (Product $product) => view('products.partials.stock', ['product' => $product, 'held' => (int) $product->held_quantity])->render())
            ->editColumn('is_active', fn (Product $product) => $product->is_active
                ? '<span class="badge badge-success">A la venta</span>'
                : '<span class="badge badge-secondary">Oculto</span>')
            ->addColumn('action', fn (Product $product) => view('products.partials.actions', [
                'product' => $product,
                'store' => $this->store(),
                'held' => (int) $product->held_quantity,
            ])->render())
            ->rawColumns(['name', 'price', 'stock', 'is_active', 'action']);
    }

    public function query(Product $model): Builder
    {
        return $model->newQuery()
            ->where('products.store_id', $this->store()->id)
            ->select('products.*')
            ->with(['category:id,name', 'photos'])
            // Units held by unpaid app orders still inside their payment window.
            ->withSum(['orderItems as held_quantity' => fn (Builder $items) => $items
                ->whereHas('order', fn (Builder $order) => $order->holdingStock())], 'quantity');
    }

    protected function defaultOrder(): array
    {
        return [0, 'asc'];
    }

    protected function getColumns(): array
    {
        return [
            Column::make('name')->title('Producto'),
            Column::make('category')->title('Categoría')->orderable(false),
            Column::make('price')->title('Precio')->searchable(false)->addClass('text-right'),
            Column::make('stock')->title('Stock')->searchable(false)->addClass('text-center'),
            Column::make('is_active')->title('Estado')->searchable(false),
            $this->actionColumn('text-right text-nowrap'),
        ];
    }

    private function store(): Store
    {
        return $this->attributes['store'];
    }
}
