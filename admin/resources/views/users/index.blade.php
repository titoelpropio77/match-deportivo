@extends('layouts.admin')

@section('title', 'Usuarios')
@section('page_title', 'Usuarios')
@section('page_subtitle', 'Lista de usuarios')
@section('breadcrumb')
    <li class="breadcrumb-item active">Usuarios</li>
@endsection

@section('content')
    <div class="card card-tabs-toolbar">
        <div class="card-header">
            <ul class="nav nav-tabs">
                <li class="nav-item"><a class="nav-link active" href="{{ route('users.index') }}"><i class="fas fa-list"></i> Lista de usuarios</a></li>
                @can('users.store')
                    <li class="nav-item"><a class="nav-link" href="{{ route('users.create') }}"><i class="fas fa-plus"></i> Crear usuario</a></li>
                @endcan
            </ul>
            @include('partials.table-toolbar', ['table' => 'users-table'])
        </div>
        <div class="card-body">
            <table id="users-table" class="table table-hover w-100">
                <thead>
                <tr>
                    <th>Id</th>
                    <th>Nombre</th>
                    <th>Nickname</th>
                    <th>Email</th>
                    <th>Teléfono</th>
                    <th>Rol</th>
                    <th>Actualizado</th>
                    <th class="no-export no-colvis">Acción</th>
                </tr>
                </thead>
            </table>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        AdminTable.init('#users-table', {
            processing: true,
            serverSide: true,
            ajax: @json(route('users.data')),
            order: [[0, 'desc']],
            columns: [
                { data: 'id', name: 'id' },
                { data: 'name', name: 'name' },
                { data: 'nickname', name: 'nickname', defaultContent: '—' },
                { data: 'email', name: 'email' },
                { data: 'phone', name: 'phone', defaultContent: '—' },
                { data: 'roles', name: 'roles', orderable: false },
                { data: 'updated_at', name: 'updated_at', searchable: false },
                { data: 'action', name: 'action', orderable: false, searchable: false },
            ],
        });
    </script>
@endpush
