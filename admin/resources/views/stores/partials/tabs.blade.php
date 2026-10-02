<ul class="nav nav-tabs">
    @can('stores.index')
        <li class="nav-item"><a class="nav-link {{ request()->routeIs('stores.index') ? 'active' : '' }}" href="{{ route('stores.index') }}"><i class="fas fa-list"></i> Lista de tiendas</a></li>
    @endcan
    @can('stores.store')
        <li class="nav-item"><a class="nav-link {{ request()->routeIs('stores.create') ? 'active' : '' }}" href="{{ route('stores.create') }}"><i class="fas fa-plus"></i> Crear tienda</a></li>
    @endcan
    @if (request()->routeIs('stores.edit'))
        <li class="nav-item"><a class="nav-link active" href="#"><i class="fas fa-edit"></i> Editar tienda</a></li>
    @endif
</ul>
