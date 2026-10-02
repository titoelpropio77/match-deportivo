<ul class="nav nav-tabs">
    <li class="nav-item"><a class="nav-link {{ request()->routeIs('stores.show') ? 'active' : '' }}" href="{{ route('stores.show', $store) }}"><i class="fas fa-boxes"></i> Productos</a></li>
    <li class="nav-item"><a class="nav-link {{ request()->routeIs('stores.movements') ? 'active' : '' }}" href="{{ route('stores.movements', $store) }}"><i class="fas fa-exchange-alt"></i> Movimientos de stock</a></li>
</ul>
