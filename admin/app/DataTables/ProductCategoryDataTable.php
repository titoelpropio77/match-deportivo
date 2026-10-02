<?php

namespace App\DataTables;

use App\Models\ProductCategory;
use Illuminate\Database\Eloquent\Builder;
use Yajra\DataTables\EloquentDataTable;
use Yajra\DataTables\Html\Column;

class ProductCategoryDataTable extends BaseDataTable
{
    public function tableId(): string
    {
        return 'product-categories-table';
    }

    public function dataTable(Builder $query): EloquentDataTable
    {
        return (new EloquentDataTable($query))
            ->editColumn('name', fn (ProductCategory $category) => ($category->icon ? '<i class="'.e($category->icon).' text-muted mr-1"></i>' : '').'<strong>'.e($category->name).'</strong>')
            ->editColumn('key', fn (ProductCategory $category) => '<code>'.e($category->key).'</code>')
            ->editColumn('stores_count', fn (ProductCategory $category) => '<span class="badge badge-secondary">'.$category->stores_count.'</span>')
            ->editColumn('products_count', fn (ProductCategory $category) => '<span class="badge badge-secondary">'.$category->products_count.'</span>')
            ->orderColumn('stores_count', 'stores_count $1')
            ->orderColumn('products_count', 'products_count $1')
            ->addColumn('action', fn (ProductCategory $category) => view('product-categories.partials.actions', ['category' => $category])->render())
            ->rawColumns(['name', 'key', 'stores_count', 'products_count', 'action']);
    }

    public function query(ProductCategory $model): Builder
    {
        return $model->newQuery()->withCount(['stores', 'products']);
    }

    protected function defaultOrder(): array
    {
        return [1, 'asc'];
    }

    protected function getColumns(): array
    {
        return [
            Column::make('id')->title('Id'),
            Column::make('name')->title('Categoría'),
            Column::make('key')->title('Clave'),
            Column::make('stores_count')->title('Tiendas')->searchable(false)->addClass('text-center'),
            Column::make('products_count')->title('Productos')->searchable(false)->addClass('text-center'),
            $this->actionColumn('text-right'),
        ];
    }
}
