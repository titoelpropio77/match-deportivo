<ul class="nav nav-tabs">
    <li class="nav-item"><a class="nav-link {{ request()->routeIs('banners.index') ? 'active' : '' }}" href="{{ route('banners.index') }}"><i class="fas fa-list"></i> Lista de banners</a></li>
    @can('banners.store')
        <li class="nav-item"><a class="nav-link {{ request()->routeIs('banners.create') ? 'active' : '' }}" href="{{ route('banners.create') }}"><i class="fas fa-plus"></i> Crear banner</a></li>
    @endcan
    @if (request()->routeIs('banners.edit'))
        <li class="nav-item"><a class="nav-link active" href="#"><i class="fas fa-edit"></i> Editar banner</a></li>
    @endif
</ul>
