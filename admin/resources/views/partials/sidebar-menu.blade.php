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

    @canany(['courts.index', 'courts.store'])
        <li class="nav-item {{ $menuOpen('courts.*') }}">
            <a href="#" class="nav-link {{ $active('courts.*') }}">
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
