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
    {!! $dataTable->table() !!}
@endsection

@push('scripts')
    {!! $dataTable->scripts() !!}
    <script>
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
