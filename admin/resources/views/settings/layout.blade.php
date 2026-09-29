@extends('layouts.admin')

@section('page_title', 'Configuración')
@section('page_subtitle', 'Roles y permisos')

@section('content')
    <div class="row">
        <div class="col-lg-3">
            <div class="card">
                <div class="card-header"><h3 class="card-title">Roles y permisos</h3>
                    <div class="card-tools"><button type="button" class="btn btn-tool" data-card-widget="collapse"><i class="fas fa-minus"></i></button></div>
                </div>
                <div class="card-body p-0">
                    <div class="list-group list-group-flush settings-nav">
                        @can('permissions.index')
                            <a href="{{ route('settings.permissions.index') }}" class="list-group-item list-group-item-action {{ request()->routeIs('settings.permissions.*') ? 'active' : '' }}"><i class="fas fa-key mr-1"></i> Permisos</a>
                        @endcan
                        @can('roles.index')
                            <a href="{{ route('settings.roles.index') }}" class="list-group-item list-group-item-action {{ request()->routeIs('settings.roles.*') ? 'active' : '' }}"><i class="fas fa-user-shield mr-1"></i> Roles</a>
                        @endcan
                        @can('users.index')
                            <a href="{{ route('users.index') }}" class="list-group-item list-group-item-action"><i class="fas fa-users mr-1"></i> Usuarios</a>
                        @endcan
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-9">
            <div class="card card-tabs-toolbar">
                <div class="card-header">
                    <ul class="nav nav-tabs">
                        @can('permissions.index')
                            <li class="nav-item"><a class="nav-link {{ request()->routeIs('settings.permissions.index') ? 'active' : '' }}" href="{{ route('settings.permissions.index') }}"><i class="fas fa-list"></i> Lista de permisos</a></li>
                        @endcan
                        @can('permissions.store')
                            <li class="nav-item"><a class="nav-link {{ request()->routeIs('settings.permissions.create') ? 'active' : '' }}" href="{{ route('settings.permissions.create') }}"><i class="fas fa-plus"></i> Crear permiso</a></li>
                        @endcan
                        @can('roles.index')
                            <li class="nav-item"><a class="nav-link {{ request()->routeIs('settings.roles.index') ? 'active' : '' }}" href="{{ route('settings.roles.index') }}"><i class="fas fa-list"></i> Lista de roles</a></li>
                        @endcan
                        @can('roles.store')
                            <li class="nav-item"><a class="nav-link {{ request()->routeIs('settings.roles.create') ? 'active' : '' }}" href="{{ route('settings.roles.create') }}"><i class="fas fa-plus"></i> Crear rol</a></li>
                        @endcan
                        @hasSection('extra_tab')
                            <li class="nav-item"><a class="nav-link active" href="#">@yield('extra_tab')</a></li>
                        @endif
                    </ul>
                    @hasSection('toolbar_table')
                        @include('partials.table-toolbar', ['table' => $__env->yieldContent('toolbar_table')])
                    @endif
                </div>
                <div class="card-body">
                    @yield('settings_content')
                </div>
            </div>
        </div>
    </div>
@endsection
