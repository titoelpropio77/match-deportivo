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
        <tbody>
        @foreach ($roles as $role)
            <tr>
                <td>{{ $role->id }}</td>
                <td><strong>{{ $role->name }}</strong></td>
                <td>{{ $role->guard_name }}</td>
                <td class="text-center">
                    @if ($role->name === 'superadmin')
                        <span class="badge badge-danger">todos</span>
                    @else
                        <span class="badge badge-primary">{{ $role->permissions_count }}</span>
                    @endif
                </td>
                <td class="text-center"><span class="badge badge-secondary">{{ $role->users_count }}</span></td>
                <td data-order="{{ $role->created_at?->timestamp }}">{{ $role->created_at?->diffForHumans() }}</td>
                <td class="text-right action-buttons">
                    @if ($role->name !== 'superadmin')
                        @can('roles.update')
                            <a href="{{ route('settings.roles.edit', $role) }}" class="btn btn-link text-primary" title="Editar"><i class="fas fa-edit"></i></a>
                        @endcan
                        @can('roles.destroy')
                            <button type="button" class="btn btn-link text-danger" title="Eliminar"
                                    data-delete-url="{{ route('settings.roles.destroy', $role) }}"
                                    data-name="el rol {{ $role->name }}"
                                    data-warning="{{ $role->users_count }} usuario(s) perderán este rol."><i class="fas fa-trash-alt"></i></button>
                        @endcan
                    @else
                        <span class="text-muted small"><i class="fas fa-lock"></i> protegido</span>
                    @endif
                </td>
            </tr>
        @endforeach
        </tbody>
    </table>
@endsection

@push('scripts')
    <script>
        AdminTable.init('#roles-table', {
            order: [[0, 'asc']],
            columnDefs: [{ targets: -1, orderable: false, searchable: false }],
        });
    </script>
@endpush
