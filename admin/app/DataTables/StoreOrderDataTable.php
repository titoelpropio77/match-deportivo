<?php

namespace App\DataTables;

use App\Http\Requests\StoreOrders\StoreOrderFilterRequest;
use App\Models\StoreOrder;
use Illuminate\Database\Eloquent\Builder;
use Yajra\DataTables\EloquentDataTable;
use Yajra\DataTables\Html\Column;

/**
 * Sales of the stores the user can see, filtered by store, status, origin and dates.
 */
class StoreOrderDataTable extends BaseDataTable
{
    public function tableId(): string
    {
        return 'store-orders-table';
    }

    public function dataTable(Builder $query): EloquentDataTable
    {
        return (new EloquentDataTable($query))
            ->editColumn('created_at', fn (StoreOrder $order) => $order->created_at->format('d/m/Y H:i'))
            ->addColumn('store', fn (StoreOrder $order) => e($order->store->name)
                .'<br><small class="text-muted">'.e($order->store->court->name).'</small>')
            ->filterColumn('store', function (Builder $query, string $keyword): void {
                $query->whereHas('store', fn (Builder $store) => $store->where('name', 'ilike', "%{$keyword}%"));
            })
            ->addColumn('customer', fn (StoreOrder $order) => e($order->customerName())
                .($order->customerPhone() ? '<br><small class="text-muted">'.e($order->customerPhone()).'</small>' : ''))
            ->filterColumn('customer', function (Builder $query, string $keyword): void {
                $query->where(fn (Builder $customer) => $customer
                    ->where('customer_name', 'ilike', "%{$keyword}%")
                    ->orWhere('customer_phone', 'ilike', "%{$keyword}%")
                    ->orWhereHas('user', fn (Builder $user) => $user
                        ->where('name', 'ilike', "%{$keyword}%")
                        ->orWhere('email', 'ilike', "%{$keyword}%")));
            })
            ->addColumn('products', fn (StoreOrder $order) => e($order->items->map(fn ($item) => $item->quantity.'× '.$item->name)->implode(', ')))
            ->filterColumn('products', function (Builder $query, string $keyword): void {
                $query->whereHas('items', fn (Builder $items) => $items->where('name', 'ilike', "%{$keyword}%"));
            })
            ->editColumn('total', fn (StoreOrder $order) => 'Bs '.number_format((float) $order->total, 2))
            ->addColumn('status_badge', fn (StoreOrder $order) => view('store-orders.partials.status', ['order' => $order])->render())
            ->editColumn('source', fn (StoreOrder $order) => $order->source === 'admin' ? 'Mostrador' : 'App')
            ->addColumn('action', fn (StoreOrder $order) => view('store-orders.partials.actions', ['order' => $order])->render())
            ->orderColumn('created_at', 'store_orders.created_at $1, store_orders.id $1')
            ->rawColumns(['store', 'customer', 'status_badge', 'action']);
    }

    public function query(StoreOrder $model, StoreOrderFilterRequest $request): Builder
    {
        $filters = $request->validated();

        $query = $model->newQuery()
            ->visibleTo($this->user())
            ->select('store_orders.*')
            ->with(['store:id,name,court_id', 'store.court:id,name', 'user:id,name,email,phone', 'items:id,store_order_id,name,quantity'])
            ->when($filters['store_id'] ?? null, fn (Builder $query, $storeId) => $query->where('store_id', $storeId))
            ->when($filters['source'] ?? null, fn (Builder $query, $source) => $query->where('source', $source))
            ->when($filters['date_from'] ?? null, fn (Builder $query, $from) => $query->whereDate('store_orders.created_at', '>=', $from))
            ->when($filters['date_to'] ?? null, fn (Builder $query, $to) => $query->whereDate('store_orders.created_at', '<=', $to));
        $this->applyStatusFilter($query, $filters['status'] ?? null);

        return $query;
    }

    protected function filtersForm(): ?string
    {
        return 'store-order-filters';
    }

    protected function defaultOrder(): array
    {
        return [1, 'desc'];
    }

    protected function getColumns(): array
    {
        return [
            Column::make('code')->title('Código'),
            Column::make('created_at')->title('Fecha')->searchable(false),
            Column::make('store')->title('Tienda')->orderable(false),
            Column::make('customer')->title('Cliente')->orderable(false),
            Column::make('products')->title('Productos')->orderable(false),
            Column::make('total')->title('Total')->searchable(false)->addClass('text-right'),
            Column::make('status_badge', 'status')->title('Estado')->orderable(false)->searchable(false),
            Column::make('source')->title('Origen')->searchable(false),
            $this->actionColumn(),
        ];
    }

    private function applyStatusFilter(Builder $query, ?string $status): void
    {
        $expiredBefore = now()->subMinutes(StoreOrder::PAYMENT_WINDOW_MINUTES);

        match ($status) {
            'pending_payment' => $query->holdingStock(),
            'expired' => $query->where('status', StoreOrder::STATUS_PENDING_PAYMENT)->where('store_orders.created_at', '<', $expiredBefore),
            'ready' => $query->where('status', StoreOrder::STATUS_PAID)->whereNull('delivered_at'),
            'delivered' => $query->where('status', StoreOrder::STATUS_PAID)->whereNotNull('delivered_at'),
            'cancelled' => $query->where('status', StoreOrder::STATUS_CANCELLED)->whereNull('paid_at'),
            'refund_pending' => $query->where('status', StoreOrder::STATUS_CANCELLED)->whereNotNull('paid_at')->whereNull('refunded_at'),
            'refunded' => $query->where('status', StoreOrder::STATUS_CANCELLED)->whereNotNull('refunded_at'),
            default => null,
        };
    }
}
