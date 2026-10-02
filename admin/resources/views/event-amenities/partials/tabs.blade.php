<ul class="nav nav-tabs">
    <li class="nav-item"><a class="nav-link {{ request()->routeIs('event-amenities.index') ? 'active' : '' }}" href="{{ route('event-amenities.index') }}"><i class="fas fa-list"></i> Lista de servicios</a></li>
    @can('event_amenities.store')
        <li class="nav-item"><a class="nav-link {{ request()->routeIs('event-amenities.create') ? 'active' : '' }}" href="{{ route('event-amenities.create') }}"><i class="fas fa-plus"></i> Crear servicio</a></li>
    @endcan
    @if (request()->routeIs('event-amenities.edit'))
        <li class="nav-item"><a class="nav-link active" href="#"><i class="fas fa-edit"></i> Editar servicio</a></li>
    @endif
</ul>
