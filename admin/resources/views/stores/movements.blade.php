@extends('layouts.admin')

@section('title', 'Movimientos de stock · '.$store->name)
@section('page_title', 'Tiendas')
@section('page_subtitle', $store->name)
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('stores.index') }}">Tiendas</a></li>
    <li class="breadcrumb-item"><a href="{{ route('stores.show', $store) }}">{{ $store->name }}</a></li>
    <li class="breadcrumb-item active">Movimientos de stock</li>
@endsection

@section('content')
    @include('stores.partials.header')

    <div class="card card-tabs-toolbar">
        <div class="card-header">
            @include('stores.partials.detail-tabs')
            @include('partials.table-toolbar', ['table' => 'stock-movements-table'])
        </div>
        <div class="card-body">
            <form id="movement-filters" class="form-row align-items-end mb-3">
                <div class="col-md-4 form-group mb-2">
                    <label for="filter-type" class="small mb-1">Tipo de movimiento</label>
                    <select id="filter-type" name="type" class="form-control form-control-sm">
                        <option value="">Todos</option>
                        @foreach ($types as $value => [$label])
                            <option value="{{ $value }}" @selected(request('type') === $value)>{{ $label }}</option>
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
        $('#movement-filters').on('change', 'select', function () { window.LaravelDataTables['stock-movements-table'].ajax.reload(); })
            .on('submit', function (event) { event.preventDefault(); });
    </script>
@endpush
