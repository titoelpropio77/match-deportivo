@extends('settings.layout')

@section('title', 'Permisos')
@section('toolbar_table', 'permissions-table')
@section('breadcrumb')
    <li class="breadcrumb-item active">Lista de permisos</li>
@endsection

@section('settings_content')
    @cannot('permissions.assign')
        <div class="alert alert-light border"><i class="fas fa-info-circle"></i> Solo lectura: no tienes el permiso <code>permissions.assign</code> para cambiar asignaciones.</div>
    @endcannot
    <table id="permissions-table" class="table table-hover permission-matrix w-100">
        <thead>
        <tr>
            <th class="no-colvis">Módulo</th>
            <th>Permiso</th>
            <th>Guard</th>
            @foreach ($roles as $role)
                <th class="text-center">{{ $role->name }}</th>
            @endforeach
            <th class="no-export no-colvis text-right">Acción</th>
        </tr>
        </thead>
        <tbody>
        @foreach ($permissions as $permission)
            @php($grantedRoleIds = $permission->roles->pluck('id'))
            <tr>
                <td>{{ explode('.', $permission->name)[0] }}</td>
                <td>{{ $permission->name }}</td>
                <td>{{ $permission->guard_name }}</td>
                @foreach ($roles as $role)
                    @php($isSuper = $role->name === 'superadmin')
                    @php($checked = $isSuper || $grantedRoleIds->contains($role->id))
                    <td class="text-center" data-order="{{ $checked ? 1 : 0 }}" data-export="{{ $checked ? 'Sí' : '' }}">
                        <div class="custom-control custom-checkbox">
                            <input type="checkbox" class="custom-control-input permission-toggle"
                                   id="perm_{{ $permission->id }}_{{ $role->id }}"
                                   data-url="{{ route('settings.permissions.toggle', [$permission, $role]) }}"
                                   @checked($checked)
                                   @disabled($isSuper || ! auth()->user()->can('permissions.assign'))
                                   @if ($isSuper) title="superadmin tiene todos los permisos" @endif>
                            <label class="custom-control-label" for="perm_{{ $permission->id }}_{{ $role->id }}"></label>
                        </div>
                    </td>
                @endforeach
                <td class="text-right action-buttons">
                    @can('permissions.update')
                        <a href="{{ route('settings.permissions.edit', $permission) }}" class="btn btn-link text-primary" title="Editar"><i class="fas fa-edit"></i></a>
                    @endcan
                    @can('permissions.destroy')
                        <button type="button" class="btn btn-link text-danger" title="Eliminar"
                                data-delete-url="{{ route('settings.permissions.destroy', $permission) }}"
                                data-name="el permiso {{ $permission->name }}"
                                data-warning="Las pantallas protegidas por este permiso quedarán accesibles solo para superadmin."><i class="fas fa-trash-alt"></i></button>
                    @endcan
                </td>
            </tr>
        @endforeach
        </tbody>
    </table>
@endsection

@push('scripts')
    <script>
        AdminTable.init('#permissions-table', {
            order: [[0, 'asc'], [1, 'asc']],
            orderFixed: [[0, 'asc']],
            rowGroup: { dataSrc: 0 },
            columnDefs: [
                { targets: 0, visible: false },
                { targets: -1, orderable: false, searchable: false },
            ],
            pageLength: 25,
        });

        $(document).on('change', '.permission-toggle', function () {
            const checkbox = this;
            $.post(checkbox.dataset.url, { granted: checkbox.checked ? 1 : 0 })
                .done(function (response) {
                    toastr.success(response.message);
                    $(checkbox).closest('td').attr('data-order', checkbox.checked ? 1 : 0);
                })
                .fail(function (xhr) {
                    checkbox.checked = !checkbox.checked;
                    toastr.error(AdminTable.errorMessage(xhr, 'No se pudo actualizar el permiso.'));
                });
        });
    </script>
@endpush
