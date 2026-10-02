<ul class="nav nav-tabs">
    <li class="nav-item"><a class="nav-link {{ request()->routeIs('product-categories.index') ? 'active' : '' }}" href="{{ route('product-categories.index') }}"><i class="fas fa-list"></i> Lista de categorías</a></li>
    @can('product_categories.store')
        <li class="nav-item"><a class="nav-link {{ request()->routeIs('product-categories.create') ? 'active' : '' }}" href="{{ route('product-categories.create') }}"><i class="fas fa-plus"></i> Crear categoría</a></li>
    @endcan
    @if (request()->routeIs('product-categories.edit'))
        <li class="nav-item"><a class="nav-link active" href="#"><i class="fas fa-edit"></i> Editar categoría</a></li>
    @endif
</ul>
