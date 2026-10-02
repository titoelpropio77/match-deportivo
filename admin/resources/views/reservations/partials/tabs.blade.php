<ul class="nav nav-tabs">
    @can('reservations.index')
        <li class="nav-item"><a class="nav-link {{ request()->routeIs('reservations.index') ? 'active' : '' }}" href="{{ route('reservations.index') }}"><i class="fas fa-list"></i> Lista de reservas</a></li>
        <li class="nav-item"><a class="nav-link {{ request()->routeIs('reservations.agenda') ? 'active' : '' }}" href="{{ route('reservations.agenda') }}"><i class="far fa-calendar-alt"></i> Agenda del día</a></li>
    @endcan
    @can('reservations.store')
        <li class="nav-item"><a class="nav-link {{ request()->routeIs('reservations.create') ? 'active' : '' }}" href="{{ route('reservations.create') }}"><i class="fas fa-plus"></i> Registrar reserva</a></li>
    @endcan
</ul>
