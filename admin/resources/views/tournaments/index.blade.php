@extends('layouts.admin')

@section('title', 'Torneos')
@section('page_title', 'Torneos')
@section('page_subtitle', 'Lista de torneos')
@section('breadcrumb')
    <li class="breadcrumb-item active">Torneos</li>
@endsection

@section('content')
    <div class="card card-tabs-toolbar">
        <div class="card-header">
            @include('tournaments.partials.tabs')
            @include('partials.table-toolbar', ['table' => 'tournaments-table'])
        </div>
        <div class="card-body">
            <form id="tournament-filters" class="form-row align-items-end mb-3">
                <div class="col-md-4 form-group mb-2">
                    <label for="filter-court" class="small mb-1">Centro deportivo</label>
                    <select id="filter-court" name="court_id" class="form-control form-control-sm">
                        <option value="">Todos</option>
                        @foreach ($courts as $court)
                            <option value="{{ $court->id }}">{{ $court->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3 form-group mb-2">
                    <label for="filter-sport" class="small mb-1">Deporte</label>
                    <select id="filter-sport" name="sport_id" class="form-control form-control-sm">
                        <option value="">Todos</option>
                        @foreach ($sports as $sport)
                            <option value="{{ $sport->id }}">{{ $sport->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3 form-group mb-2">
                    <label for="filter-status" class="small mb-1">Estado</label>
                    <select id="filter-status" name="status" class="form-control form-control-sm">
                        <option value="">Todos</option>
                        @foreach ($statuses as $value => [$label])
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
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
            const $filters = $('#tournament-filters');
            const reload = function () { window.LaravelDataTables['tournaments-table'].ajax.reload(); };
            $filters.on('change', 'select, input', reload);
            $filters.on('reset', function () { setTimeout(reload); });
            $filters.on('submit', function (event) { event.preventDefault(); });
        })();
    </script>
@endpush
