@php
    $menuOpen = fn (string ...$patterns) => request()->routeIs(...$patterns) ? 'menu-open' : '';
    $active = fn (string ...$patterns) => request()->routeIs(...$patterns) ? 'active' : '';
@endphp
<ul class="nav nav-pills nav-sidebar flex-column nav-child-indent" data-widget="treeview" role="menu" data-accordion="false">
    @can('dashboard.index')
        <li class="nav-item">
            <a href="{{ route('dashboard') }}" class="nav-link {{ $active('dashboard') }}">
                <i class="nav-icon fas fa-tachometer-alt"></i>
                <p>Dashboard</p>
            </a>
        </li>
    @endcan

    @canany(['courts.index', 'courts.store', 'court_features.index', 'event_amenities.index'])
        <li class="nav-item {{ $menuOpen('courts.*', 'court-features.*', 'event-amenities.*') }}">
            <a href="#" class="nav-link {{ $active('courts.*', 'court-features.*', 'event-amenities.*') }}">
                <i class="nav-icon fas fa-map-marked-alt"></i>
                <p>Centros deportivos <i class="right fas fa-angle-left"></i></p>
            </a>
            <ul class="nav nav-treeview">
                @can('courts.index')
                    <li class="nav-item">
                        <a href="{{ route('courts.index') }}" class="nav-link {{ $active('courts.index', 'courts.show', 'courts.edit') }}">
                            <i class="far fa-circle nav-icon"></i><p>Lista de centros deportivos</p>
                        </a>
                    </li>
                @endcan
                @can('courts.store')
                    <li class="nav-item">
                        <a href="{{ route('courts.create') }}" class="nav-link {{ $active('courts.create') }}">
                            <i class="far fa-circle nav-icon"></i><p>Crear centro deportivo</p>
                        </a>
                    </li>
                @endcan
                @can('court_features.index')
                    <li class="nav-item">
                        <a href="{{ route('court-features.index') }}" class="nav-link {{ $active('court-features.*') }}">
                            <i class="far fa-circle nav-icon"></i><p>Características de canchas</p>
                        </a>
                    </li>
                @endcan
                @can('event_amenities.index')
                    <li class="nav-item">
                        <a href="{{ route('event-amenities.index') }}" class="nav-link {{ $active('event-amenities.*') }}">
                            <i class="far fa-circle nav-icon"></i><p>Servicios de espacios para eventos</p>
                        </a>
                    </li>
                @endcan
            </ul>
        </li>
    @endcanany

    @canany(['reservations.index', 'reservations.store'])
        <li class="nav-item {{ $menuOpen('reservations.*') }}">
            <a href="#" class="nav-link {{ $active('reservations.*') }}">
                <i class="nav-icon fas fa-calendar-check"></i>
                <p>Reservas <i class="right fas fa-angle-left"></i></p>
            </a>
            <ul class="nav nav-treeview">
                @can('reservations.index')
                    <li class="nav-item">
                        <a href="{{ route('reservations.index') }}" class="nav-link {{ $active('reservations.index', 'reservations.show') }}">
                            <i class="far fa-circle nav-icon"></i><p>Lista de reservas</p>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="{{ route('reservations.agenda') }}" class="nav-link {{ $active('reservations.agenda') }}">
                            <i class="far fa-circle nav-icon"></i><p>Agenda del día</p>
                        </a>
                    </li>
                @endcan
                @can('reservations.store')
                    <li class="nav-item">
                        <a href="{{ route('reservations.create') }}" class="nav-link {{ $active('reservations.create') }}">
                            <i class="far fa-circle nav-icon"></i><p>Registrar reserva</p>
                        </a>
                    </li>
                @endcan
            </ul>
        </li>
    @endcanany

    @canany(['event_reservations.index', 'event_reservations.store'])
        <li class="nav-item {{ $menuOpen('event-reservations.*') }}">
            <a href="#" class="nav-link {{ $active('event-reservations.*') }}">
                <i class="nav-icon fas fa-glass-cheers"></i>
                <p>Eventos <i class="right fas fa-angle-left"></i></p>
            </a>
            <ul class="nav nav-treeview">
                @can('event_reservations.index')
                    <li class="nav-item">
                        <a href="{{ route('event-reservations.index') }}" class="nav-link {{ $active('event-reservations.index', 'event-reservations.show') }}">
                            <i class="far fa-circle nav-icon"></i><p>Reservas de espacios</p>
                        </a>
                    </li>
                @endcan
                @can('event_reservations.store')
                    <li class="nav-item">
                        <a href="{{ route('event-reservations.create') }}" class="nav-link {{ $active('event-reservations.create') }}">
                            <i class="far fa-circle nav-icon"></i><p>Registrar reserva de evento</p>
                        </a>
                    </li>
                @endcan
            </ul>
        </li>
    @endcanany

    @canany(['stores.index', 'stores.store', 'store_orders.index', 'store_orders.store', 'product_categories.index'])
        <li class="nav-item {{ $menuOpen('stores.*', 'store-orders.*', 'product-categories.*') }}">
            <a href="#" class="nav-link {{ $active('stores.*', 'store-orders.*', 'product-categories.*') }}">
                <i class="nav-icon fas fa-store"></i>
                <p>Tiendas <i class="right fas fa-angle-left"></i></p>
            </a>
            <ul class="nav nav-treeview">
                @can('stores.index')
                    <li class="nav-item">
                        <a href="{{ route('stores.index') }}" class="nav-link {{ $active('stores.index', 'stores.show', 'stores.movements', 'stores.edit', 'stores.products.*') }}">
                            <i class="far fa-circle nav-icon"></i><p>Lista de tiendas</p>
                        </a>
                    </li>
                @endcan
                @can('stores.store')
                    <li class="nav-item">
                        <a href="{{ route('stores.create') }}" class="nav-link {{ $active('stores.create') }}">
                            <i class="far fa-circle nav-icon"></i><p>Crear tienda</p>
                        </a>
                    </li>
                @endcan
                @can('store_orders.index')
                    <li class="nav-item">
                        <a href="{{ route('store-orders.index') }}" class="nav-link {{ $active('store-orders.index', 'store-orders.show') }}">
                            <i class="far fa-circle nav-icon"></i><p>Ventas</p>
                        </a>
                    </li>
                @endcan
                @can('store_orders.store')
                    <li class="nav-item">
                        <a href="{{ route('store-orders.create') }}" class="nav-link {{ $active('store-orders.create') }}">
                            <i class="far fa-circle nav-icon"></i><p>Registrar venta</p>
                        </a>
                    </li>
                @endcan
                @can('product_categories.index')
                    <li class="nav-item">
                        <a href="{{ route('product-categories.index') }}" class="nav-link {{ $active('product-categories.*') }}">
                            <i class="far fa-circle nav-icon"></i><p>Categorías de productos</p>
                        </a>
                    </li>
                @endcan
            </ul>
        </li>
    @endcanany

    @canany(['tournaments.index', 'tournaments.store'])
        <li class="nav-item {{ $menuOpen('tournaments.*') }}">
            <a href="#" class="nav-link {{ $active('tournaments.*') }}">
                <i class="nav-icon fas fa-trophy"></i>
                <p>Torneos <i class="right fas fa-angle-left"></i></p>
            </a>
            <ul class="nav nav-treeview">
                @can('tournaments.index')
                    <li class="nav-item">
                        <a href="{{ route('tournaments.index') }}" class="nav-link {{ $active('tournaments.index', 'tournaments.show', 'tournaments.edit') }}">
                            <i class="far fa-circle nav-icon"></i><p>Lista de torneos</p>
                        </a>
                    </li>
                @endcan
                @can('tournaments.store')
                    <li class="nav-item">
                        <a href="{{ route('tournaments.create') }}" class="nav-link {{ $active('tournaments.create') }}">
                            <i class="far fa-circle nav-icon"></i><p>Crear torneo</p>
                        </a>
                    </li>
                @endcan
            </ul>
        </li>
    @endcanany

    @canany(['users.index', 'users.store'])
        <li class="nav-item {{ $menuOpen('users.*') }}">
            <a href="#" class="nav-link {{ $active('users.*') }}">
                <i class="nav-icon fas fa-users"></i>
                <p>Usuarios <i class="right fas fa-angle-left"></i></p>
            </a>
            <ul class="nav nav-treeview">
                @can('users.index')
                    <li class="nav-item">
                        <a href="{{ route('users.index') }}" class="nav-link {{ $active('users.index', 'users.show', 'users.edit') }}">
                            <i class="far fa-circle nav-icon"></i><p>Lista de usuarios</p>
                        </a>
                    </li>
                @endcan
                @can('users.store')
                    <li class="nav-item">
                        <a href="{{ route('users.create') }}" class="nav-link {{ $active('users.create') }}">
                            <i class="far fa-circle nav-icon"></i><p>Crear usuario</p>
                        </a>
                    </li>
                @endcan
            </ul>
        </li>
    @endcanany

    @canany(['permissions.index', 'permissions.store', 'roles.index', 'roles.store'])
        <li class="nav-header">SISTEMA</li>
        <li class="nav-item {{ $menuOpen('settings.*') }}">
            <a href="#" class="nav-link {{ $active('settings.*') }}">
                <i class="nav-icon fas fa-cogs"></i>
                <p>Configuración <i class="right fas fa-angle-left"></i></p>
            </a>
            <ul class="nav nav-treeview">
                <li class="nav-item {{ $menuOpen('settings.*') }}">
                    <a href="#" class="nav-link">
                        <i class="nav-icon fas fa-user-shield"></i>
                        <p>Roles y permisos <i class="right fas fa-angle-left"></i></p>
                    </a>
                    <ul class="nav nav-treeview">
                        @can('permissions.index')
                            <li class="nav-item">
                                <a href="{{ route('settings.permissions.index') }}" class="nav-link {{ $active('settings.permissions.index', 'settings.permissions.edit') }}">
                                    <i class="far fa-circle nav-icon"></i><p>Lista de permisos</p>
                                </a>
                            </li>
                        @endcan
                        @can('permissions.store')
                            <li class="nav-item">
                                <a href="{{ route('settings.permissions.create') }}" class="nav-link {{ $active('settings.permissions.create') }}">
                                    <i class="far fa-circle nav-icon"></i><p>Crear permiso</p>
                                </a>
                            </li>
                        @endcan
                        @can('roles.index')
                            <li class="nav-item">
                                <a href="{{ route('settings.roles.index') }}" class="nav-link {{ $active('settings.roles.index', 'settings.roles.edit') }}">
                                    <i class="far fa-circle nav-icon"></i><p>Lista de roles</p>
                                </a>
                            </li>
                        @endcan
                        @can('roles.store')
                            <li class="nav-item">
                                <a href="{{ route('settings.roles.create') }}" class="nav-link {{ $active('settings.roles.create') }}">
                                    <i class="far fa-circle nav-icon"></i><p>Crear rol</p>
                                </a>
                            </li>
                        @endcan
                    </ul>
                </li>
            </ul>
        </li>
    @endcanany
</ul>
