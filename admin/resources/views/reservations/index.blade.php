@extends('layouts.admin')

@section('title', 'Reservas')
@section('page_title', 'Reservas')
@section('page_subtitle', 'Lista de reservas')
@section('breadcrumb')
    <li class="breadcrumb-item active">Reservas</li>
@endsection

@section('content')
    <div class="card card-tabs-toolbar">
        <div class="card-header">
            @include('reservations.partials.tabs')
            @include('partials.table-toolbar', ['table' => 'reservations-table'])
        </div>
        <div class="card-body">
            <form id="reservation-filters" class="form-row align-items-end mb-3">
                <div class="col-md-3 form-group mb-2">
                    <label for="filter-court" class="small mb-1">Centro deportivo</label>
                    <select id="filter-court" name="court_id" class="form-control form-control-sm">
                        <option value="">Todos</option>
                        @foreach ($courts as $court)
                            <option value="{{ $court->id }}" @selected((string) ($filters['court_id'] ?? '') === (string) $court->id)>{{ $court->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2 form-group mb-2">
                    <label for="filter-status" class="small mb-1">Estado</label>
                    <select id="filter-status" name="status" class="form-control form-control-sm">
                        <option value="">Todos</option>
                        @foreach ($statuses as $value => $label)
                            <option value="{{ $value }}" @selected(($filters['status'] ?? '') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2 form-group mb-2">
                    <label for="filter-source" class="small mb-1">Origen</label>
                    <select id="filter-source" name="source" class="form-control form-control-sm">
                        <option value="">Todos</option>
                        @foreach ($sources as $value => $label)
                            <option value="{{ $value }}" @selected(($filters['source'] ?? '') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2 form-group mb-2">
                    <label for="filter-from" class="small mb-1">Desde</label>
                    <input type="date" id="filter-from" name="date_from" class="form-control form-control-sm" value="{{ $filters['date_from'] ?? '' }}">
                </div>
                <div class="col-md-2 form-group mb-2">
                    <label for="filter-to" class="small mb-1">Hasta</label>
                    <input type="date" id="filter-to" name="date_to" class="form-control form-control-sm" value="{{ $filters['date_to'] ?? '' }}">
                </div>
                <div class="col-md-1 form-group mb-2">
                    <button type="reset" class="btn btn-sm btn-default btn-block" title="Limpiar filtros"><i class="fas fa-eraser"></i></button>
                </div>
            </form>

            <table id="reservations-table" class="table table-hover w-100">
                <thead>
                <tr>
                    <th>Referencia</th>
                    <th>Fecha</th>
                    <th>Horario</th>
                    <th>Centro / cancha</th>
                    <th>Deporte</th>
                    <th>Cliente</th>
                    <th>Monto</th>
                    <th>Estado</th>
                    <th>Origen</th>
                    <th class="no-export no-colvis">Acción</th>
                </tr>
                </thead>
            </table>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        (function () {
            const $filters = $('#reservation-filters');
            const table = AdminTable.init('#reservations-table', {
                processing: true,
                serverSide: true,
                stateSave: false,
                ajax: {
                    url: @json(route('reservations.data')),
                    data: function (params) {
                        $filters.serializeArray().forEach(function (field) {
                            if (field.value) params[field.name] = field.value;
                        });
                    },
                },
                order: [[1, 'desc']],
                columns: [
                    { data: 'reference', name: 'reference', orderable: false },
                    { data: 'reserved_on', name: 'reserved_on', searchable: false },
                    { data: 'schedule', name: 'schedule', searchable: false },
                    { data: 'venue', name: 'venue', orderable: false },
                    { data: 'sport', name: 'sport', orderable: false, searchable: false },
                    { data: 'customer', name: 'customer', orderable: false },
                    { data: 'amount', name: 'amount', searchable: false, className: 'text-right' },
                    { data: 'status_badge', name: 'status', orderable: false, searchable: false },
                    { data: 'source', name: 'source', searchable: false },
                    { data: 'action', name: 'action', orderable: false, searchable: false },
                ],
            });

            $filters.on('change', 'select, input', function () { table.ajax.reload(); });
            $filters.on('reset', function () { setTimeout(function () { table.ajax.reload(); }); });
            $filters.on('submit', function (event) { event.preventDefault(); });
        })();
    </script>
@endpush
