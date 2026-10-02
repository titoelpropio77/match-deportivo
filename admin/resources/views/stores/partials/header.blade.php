{{-- Store summary shown above the products and stock movements tabs. --}}
@php($bs = fn ($amount) => 'Bs '.number_format((float) $amount, 2))
<div class="card card-primary card-outline">
    <div class="card-body">
        <div class="d-flex flex-wrap align-items-start">
            @if ($store->coverUrl())
                <img src="{{ $store->coverUrl() }}" alt="{{ $store->name }}" class="rounded mr-3 mb-2" style="width: 140px; height: 90px; object-fit: cover;">
            @endif
            <div class="flex-grow-1 mb-2">
                <h4 class="mb-1">
                    {{ $store->name }}
                    @unless ($store->is_active)<span class="badge badge-secondary align-middle">Inactiva · no se ve en la app</span>@endunless
                </h4>
                <div class="text-muted">
                    <i class="fas fa-map-marked-alt mr-1"></i>
                    @can('courts.show')
                        <a href="{{ route('courts.show', $store->court) }}">{{ $store->court->name }}</a>
                    @else
                        {{ $store->court->name }}
                    @endcan
                    @if ($store->phone)<span class="ml-3"><i class="fab fa-whatsapp mr-1"></i>{{ $store->phone }}</span>@endif
                </div>
                <div class="mt-1">
                    @foreach ($store->categories as $category)
                        <span class="badge badge-info">{{ $category->name }}</span>
                    @endforeach
                </div>
            </div>
            <div class="mb-2">
                @can('store_orders.store')
                    <a href="{{ route('store-orders.create', ['store_id' => $store->id]) }}" class="btn btn-sm btn-success"><i class="fas fa-cash-register"></i> Registrar venta</a>
                @endcan
                @can('store_orders.index')
                    <a href="{{ route('store-orders.index', ['store_id' => $store->id]) }}" class="btn btn-sm btn-default"><i class="fas fa-receipt"></i> Ventas</a>
                @endcan
                @can('stores.update')
                    <a href="{{ route('stores.edit', $store) }}" class="btn btn-sm btn-primary"><i class="fas fa-edit"></i> Editar</a>
                @endcan
            </div>
        </div>
        <div class="row text-center mt-2">
            <div class="col-6 col-md border-right">
                <div class="h4 mb-0">{{ $store->active_products_count }}<small class="text-muted">/{{ $store->products_count }}</small></div>
                <small class="text-muted">Productos a la venta</small>
            </div>
            <div class="col-6 col-md border-right">
                <div class="h4 mb-0">{{ (int) $store->units_in_stock }}</div>
                <small class="text-muted">Unidades en stock</small>
            </div>
            <div class="col-6 col-md border-right">
                <div class="h4 mb-0 {{ $store->low_stock_count > 0 ? 'text-warning' : '' }}">{{ $store->low_stock_count }}</div>
                <small class="text-muted">Con stock bajo</small>
            </div>
            <div class="col-6 col-md border-right">
                <div class="h4 mb-0 {{ $store->to_deliver_count > 0 ? 'text-info' : '' }}">
                    @can('store_orders.index')
                        <a href="{{ route('store-orders.index', ['store_id' => $store->id, 'status' => 'ready']) }}" class="text-reset">{{ $store->to_deliver_count }}</a>
                    @else
                        {{ $store->to_deliver_count }}
                    @endcan
                </div>
                <small class="text-muted">Pedidos por entregar</small>
            </div>
            <div class="col-12 col-md">
                <div class="h4 mb-0 text-success">{{ $bs($store->month_sales) }}</div>
                <small class="text-muted">Ventas de {{ now()->locale('es')->isoFormat('MMMM') }}</small>
            </div>
        </div>
    </div>
</div>
