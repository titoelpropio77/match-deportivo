<ul class="nav nav-tabs">
    <li class="nav-item"><a class="nav-link {{ request()->routeIs('court-features.index') ? 'active' : '' }}" href="{{ route('court-features.index') }}"><i class="fas fa-list"></i> Lista de características</a></li>
    @can('court_features.store')
        <li class="nav-item"><a class="nav-link {{ request()->routeIs('court-features.create') ? 'active' : '' }}" href="{{ route('court-features.create') }}"><i class="fas fa-plus"></i> Crear característica</a></li>
    @endcan
    @if (request()->routeIs('court-features.edit'))
        <li class="nav-item"><a class="nav-link active" href="#"><i class="fas fa-edit"></i> Editar característica</a></li>
    @endif
</ul>
