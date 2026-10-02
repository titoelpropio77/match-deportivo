<ul class="nav nav-tabs">
    @can('store_orders.index')
        <li class="nav-item"><a class="nav-link {{ request()->routeIs('store-orders.index') ? 'active' : '' }}" href="{{ route('store-orders.index') }}"><i class="fas fa-list"></i> Ventas</a></li>
    @endcan
    @can('store_orders.store')
        <li class="nav-item"><a class="nav-link {{ request()->routeIs('store-orders.create') ? 'active' : '' }}" href="{{ route('store-orders.create') }}"><i class="fas fa-cash-register"></i> Registrar venta</a></li>
    @endcan
</ul>
