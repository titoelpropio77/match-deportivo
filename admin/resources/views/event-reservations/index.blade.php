@extends('layouts.admin')

@section('title', 'Eventos')
@section('page_title', 'Eventos')
@section('page_subtitle', 'Reservas de espacios para eventos')
@section('breadcrumb')
    <li class="breadcrumb-item active">Eventos</li>
@endsection

@section('content')
    <div class="card card-tabs-toolbar">
        <div class="card-header">
            @include('event-reservations.partials.tabs')
            @include('partials.table-toolbar', ['table' => 'event-reservations-table'])
        </div>
        <div class="card-body">
            <form id="event-reservation-filters" class="form-row align-items-end mb-3">
                <div class="col-md-4 form-group mb-2">
                    <label for="filter-court" class="small mb-1">Centro deportivo</label>
                    <select id="filter-court" name="court_id" class="form-control form-control-sm">
                        <option value="">Todos</option>
                        @foreach ($courts as $court)
                            <option value="{{ $court->id }}" @selected((string) ($filters['court_id'] ?? '') === (string) $court->id)>{{ $court->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3 form-group mb-2">
                    <label for="filter-status" class="small mb-1">Estado</label>
                    <select id="filter-status" name="status" class="form-control form-control-sm">
                        <option value="">Todos</option>
                        @foreach ($statuses as $value => $label)
                            <option value="{{ $value }}" @selected(($filters['status'] ?? '') === $value)>{{ $label }}</option>
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

            {!! $dataTable->table() !!}
        </div>
    </div>
@endsection

@push('scripts')
    {!! $dataTable->scripts() !!}
    <script>
        (function () {
            const $filters = $('#event-reservation-filters');
            const reload = function () { window.LaravelDataTables['event-reservations-table'].ajax.reload(); };
            $filters.on('change', 'select, input', reload);
            $filters.on('reset', function () { setTimeout(reload); });
            $filters.on('submit', function (event) { event.preventDefault(); });
        })();
    </script>
@endpush
