<ul class="nav nav-tabs">
    @can('event_reservations.index')
        <li class="nav-item"><a class="nav-link {{ request()->routeIs('event-reservations.index') ? 'active' : '' }}" href="{{ route('event-reservations.index') }}"><i class="fas fa-list"></i> Reservas de espacios</a></li>
    @endcan
    @can('event_reservations.store')
        <li class="nav-item"><a class="nav-link {{ request()->routeIs('event-reservations.create') ? 'active' : '' }}" href="{{ route('event-reservations.create') }}"><i class="fas fa-plus"></i> Registrar reserva</a></li>
    @endcan
</ul>
