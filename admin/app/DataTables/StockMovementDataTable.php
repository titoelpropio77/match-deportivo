<?php

namespace App\DataTables;

use App\Models\StockMovement;
use App\Models\Store;
use Illuminate\Database\Eloquent\Builder;
use Yajra\DataTables\EloquentDataTable;
use Yajra\DataTables\Html\Column;

/**
 * Stock ledger of one store (passed with ->with('store', $store)), newest first.
 */
class StockMovementDataTable extends BaseDataTable
{
    public function tableId(): string
    {
        return 'stock-movements-table';
    }

    public function dataTable(Builder $query): EloquentDataTable
    {
        return (new EloquentDataTable($query))
            ->editColumn('created_at', fn (StockMovement $movement) => $movement->created_at->format('d/m/Y H:i'))
            ->addColumn('product', fn (StockMovement $movement) => e($movement->product->name))
            ->filterColumn('product', function (Builder $query, string $keyword): void {
                $query->whereHas('product', fn (Builder $product) => $product->where('name', 'ilike', "%{$keyword}%"));
            })
            ->editColumn('type', fn (StockMovement $movement) => '<span class="badge badge-'.$movement->typeColor().'">'.e($movement->typeLabel()).'</span>')
            ->editColumn('quantity', fn (StockMovement $movement) => '<strong class="text-'.($movement->quantity > 0 ? 'success' : 'danger').'">'
                .sprintf('%+d', $movement->quantity).'</strong>')
            ->editColumn('reason', fn (StockMovement $movement) => $movement->order
                ? (auth()->user()->can('store_orders.show')
                    ? '<a href="'.route('store-orders.show', $movement->order).'">Venta '.e($movement->order->code).'</a>'
                    : 'Venta '.e($movement->order->code))
                    .($movement->reason ? ' · '.e($movement->reason) : '')
                : e($movement->reason ?? '—'))
            ->addColumn('user', fn (StockMovement $movement) => e($movement->user?->name ?? ($movement->order?->source === 'app' ? 'App' : '—')))
            ->rawColumns(['type', 'quantity', 'reason']);
    }

    public function query(StockMovement $model): Builder
    {
        /** @var Store $store */
        $store = $this->attributes['store'];
        $filters = request()->validate([
            'type' => ['nullable', 'in:'.implode(',', array_keys(StockMovement::TYPES))],
        ]);

        return $model->newQuery()
            ->select('stock_movements.*')
            ->whereHas('product', fn (Builder $product) => $product->where('store_id', $store->id))
            ->with(['product:id,name', 'order:id,code,source', 'user:id,name'])
            ->when($filters['type'] ?? null, fn (Builder $query, string $type) => $query->where('type', $type));
    }

    protected function filtersForm(): ?string
    {
        return 'movement-filters';
    }

    protected function defaultOrder(): array
    {
        return [0, 'desc'];
    }

    protected function getColumns(): array
    {
        return [
            Column::make('created_at')->title('Fecha')->searchable(false),
            Column::make('product')->title('Producto')->orderable(false),
            Column::make('type')->title('Movimiento')->searchable(false),
            Column::make('quantity')->title('Cantidad')->searchable(false)->addClass('text-center'),
            Column::make('stock_after')->title('Stock resultante')->searchable(false)->addClass('text-center'),
            Column::make('reason')->title('Detalle'),
            Column::make('user')->title('Registrado por')->orderable(false)->searchable(false),
        ];
    }
}
