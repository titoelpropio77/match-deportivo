@extends('settings.layout')

@section('title', 'Roles')
@section('toolbar_table', 'roles-table')
@section('breadcrumb')
    <li class="breadcrumb-item active">Lista de roles</li>
@endsection

@section('settings_content')
    <table id="roles-table" class="table table-hover w-100">
        <thead>
        <tr>
            <th>Id</th>
            <th>Rol</th>
            <th>Guard</th>
            <th class="text-center">Permisos</th>
            <th class="text-center">Usuarios</th>
            <th>Creado</th>
            <th class="no-export no-colvis text-right">Acción</th>
        </tr>
        </thead>
    </table>
@endsection

@push('scripts')
    <script>
        AdminTable.init('#roles-table', {
            processing: true,
            serverSide: true,
            ajax: @json(route('settings.roles.data')),
            order: [[0, 'asc']],
            columns: [
                { data: 'id', name: 'id' },
                { data: 'name', name: 'name' },
                { data: 'guard_name', name: 'guard_name' },
                { data: 'permissions_count', name: 'permissions_count', searchable: false, className: 'text-center' },
                { data: 'users_count', name: 'users_count', searchable: false, className: 'text-center' },
                { data: 'created_at', name: 'created_at', searchable: false },
                { data: 'action', name: 'action', orderable: false, searchable: false },
            ],
        });
    </script>
@endpush
