@extends('layouts.admin')

@section('title', 'Ventas')
@section('page_title', 'Tiendas')
@section('page_subtitle', 'Ventas de las tiendas')
@section('breadcrumb')
    <li class="breadcrumb-item active">Ventas</li>
@endsection

@section('content')
    <div class="card card-tabs-toolbar">
        <div class="card-header">
            @include('store-orders.partials.tabs')
            @include('partials.table-toolbar', ['table' => 'store-orders-table'])
        </div>
        <div class="card-body">
            <form id="store-order-filters" class="form-row align-items-end mb-3">
                <div class="col-md-3 form-group mb-2">
                    <label for="filter-store" class="small mb-1">Tienda</label>
                    <select id="filter-store" name="store_id" class="form-control form-control-sm">
                        <option value="">Todas</option>
                        @foreach ($stores as $store)
                            <option value="{{ $store->id }}" @selected((string) ($filters['store_id'] ?? '') === (string) $store->id)>{{ $store->name }} · {{ $store->court->name }}</option>
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
                    <label for="filter-source" class="small mb-1">Origen</label>
                    <select id="filter-source" name="source" class="form-control form-control-sm">
                        <option value="">Todos</option>
                        <option value="app" @selected(($filters['source'] ?? '') === 'app')>App</option>
                        <option value="admin" @selected(($filters['source'] ?? '') === 'admin')>Mostrador</option>
                    </select>
                </div>
                <div class="col-md-2 form-group mb-2">
                    <label for="filter-from" class="small mb-1">Desde</label>
                    <input type="date" id="filter-from" name="date_from" class="form-control form-control-sm" value="{{ $filters['date_from'] ?? '' }}">
                </div>
                <div class="col-md-1 form-group mb-2">
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
            const $filters = $('#store-order-filters');
            const reload = function () { window.LaravelDataTables['store-orders-table'].ajax.reload(); };
            $filters.on('change', 'select, input', reload);
            $filters.on('reset', function () { setTimeout(reload); });
            $filters.on('submit', function (event) { event.preventDefault(); });
        })();
    </script>
@endpush
