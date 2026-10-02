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
            <table id="tournaments-table" class="table table-hover w-100">
                <thead>
                <tr>
                    <th>Id</th>
                    <th>Torneo</th>
                    <th>Centro deportivo</th>
                    <th>Deporte</th>
                    <th>Inicio</th>
                    <th class="text-center">Equipos</th>
                    <th>Inscripción</th>
                    <th>Estado</th>
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
            const $filters = $('#tournament-filters');
            const table = AdminTable.init('#tournaments-table', {
                processing: true,
                serverSide: true,
                stateSave: false,
                ajax: {
                    url: @json(route('tournaments.data')),
                    data: function (params) {
                        $filters.serializeArray().forEach(function (field) {
                            if (field.value) params[field.name] = field.value;
                        });
                    },
                },
                order: [[4, 'desc']],
                columns: [
                    { data: 'id', name: 'id' },
                    { data: 'name', name: 'name' },
                    { data: 'venue', name: 'venue', orderable: false },
                    { data: 'sport', name: 'sport', orderable: false, searchable: false },
                    { data: 'starts_on', name: 'starts_on', searchable: false },
                    { data: 'teams', name: 'teams', orderable: false, searchable: false, className: 'text-center' },
                    { data: 'entry_fee', name: 'entry_fee', searchable: false },
                    { data: 'status_badge', name: 'status', orderable: false, searchable: false },
                    { data: 'action', name: 'action', orderable: false, searchable: false },
                ],
            });
            $filters.on('change', 'select', function () { table.ajax.reload(); });
        })();
    </script>
@endpush
