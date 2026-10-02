<ul class="nav nav-tabs">
    <li class="nav-item"><a class="nav-link {{ request()->routeIs('tournaments.index') ? 'active' : '' }}" href="{{ route('tournaments.index') }}"><i class="fas fa-list"></i> Lista de torneos</a></li>
    @can('tournaments.store')
        <li class="nav-item"><a class="nav-link {{ request()->routeIs('tournaments.create') ? 'active' : '' }}" href="{{ route('tournaments.create') }}"><i class="fas fa-plus"></i> Crear torneo</a></li>
    @endcan
</ul>
