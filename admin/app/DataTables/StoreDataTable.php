<?php

namespace App\DataTables;

use App\Models\ProductCategory;
use App\Models\Store;
use Illuminate\Database\Eloquent\Builder;
use Yajra\DataTables\EloquentDataTable;
use Yajra\DataTables\Html\Column;

/**
 * Stores of the sports centers the user can see.
 */
class StoreDataTable extends BaseDataTable
{
    public function tableId(): string
    {
        return 'stores-table';
    }

    public function dataTable(Builder $query): EloquentDataTable
    {
        return (new EloquentDataTable($query))
            ->editColumn('name', fn (Store $store) => '<a href="'.route('stores.show', $store).'"><strong>'.e($store->name).'</strong></a>'
                .($store->phone ? '<br><small class="text-muted"><i class="fab fa-whatsapp"></i> '.e($store->phone).'</small>' : ''))
            ->addColumn('venue', fn (Store $store) => e($store->court->name))
            ->filterColumn('venue', function (Builder $query, string $keyword): void {
                $query->whereHas('court', fn (Builder $court) => $court->where('name', 'ilike', "%{$keyword}%"));
            })
            ->addColumn('categories', fn (Store $store) => $store->categories
                ->map(fn (ProductCategory $category) => '<span class="badge badge-info mr-1">'.e($category->name).'</span>')
                ->implode(''))
            ->editColumn('products_count', fn (Store $store) => $store->products_count
                .($store->low_stock_count > 0 ? ' <span class="badge badge-warning" title="Productos con stock bajo"><i class="fas fa-exclamation-triangle"></i> '.$store->low_stock_count.'</span>' : ''))
            ->orderColumn('products_count', 'products_count $1')
            ->editColumn('is_active', fn (Store $store) => $store->is_active
                ? '<span class="badge badge-success">Activa</span>'
                : '<span class="badge badge-secondary">Inactiva</span>')
            ->addColumn('action', fn (Store $store) => view('stores.partials.actions', ['store' => $store])->render())
            ->rawColumns(['name', 'categories', 'products_count', 'is_active', 'action']);
    }

    public function query(Store $model): Builder
    {
        return $model->newQuery()
            ->visibleTo($this->user())
            ->select('stores.*')
            ->with(['court:id,name', 'categories:id,name'])
            ->withCount([
                'products',
                'products as low_stock_count' => fn (Builder $products) => $products->lowStock(),
                'orders',
            ]);
    }

    protected function defaultOrder(): array
    {
        return [1, 'asc'];
    }

    protected function getColumns(): array
    {
        return [
            Column::make('id')->title('Id'),
            Column::make('name')->title('Tienda'),
            Column::make('venue')->title('Centro deportivo')->orderable(false),
            Column::make('categories')->title('Categorías')->orderable(false)->searchable(false),
            Column::make('products_count')->title('Productos')->searchable(false)->addClass('text-center'),
            Column::make('is_active')->title('Estado')->searchable(false),
            $this->actionColumn('text-right'),
        ];
    }
}
