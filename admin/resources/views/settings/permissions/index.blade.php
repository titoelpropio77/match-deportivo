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
    </table>
@endsection

@push('scripts')
    <script>
        AdminTable.init('#permissions-table', {
            processing: true,
            serverSide: true,
            ajax: @json(route('settings.permissions.data')),
            order: [[1, 'asc']],
            orderFixed: [[0, 'asc']],
            rowGroup: { dataSrc: 'module' },
            columns: [
                { data: 'module', name: 'module', visible: false },
                { data: 'name', name: 'name' },
                { data: 'guard_name', name: 'guard_name' },
                @foreach ($roles as $role)
                    { data: @json('role_'.$role->id), name: @json('role_'.$role->id), orderable: false, searchable: false, className: 'text-center' },
                @endforeach
                { data: 'action', name: 'action', orderable: false, searchable: false },
            ],
            pageLength: 25,
        });

        $(document).on('change', '.permission-toggle', function () {
            const checkbox = this;
            $.post(checkbox.dataset.url, { granted: checkbox.checked ? 1 : 0 })
                .done(function (response) {
                    toastr.success(response.message);
                    $(checkbox).siblings('label').find('.d-none').text(checkbox.checked ? 'Sí' : '');
                })
                .fail(function (xhr) {
                    checkbox.checked = !checkbox.checked;
                    toastr.error(AdminTable.errorMessage(xhr, 'No se pudo actualizar el permiso.'));
                });
        });
    </script>
@endpush
