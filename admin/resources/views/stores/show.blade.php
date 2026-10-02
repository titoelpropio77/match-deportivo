@extends('layouts.admin')

@section('title', $store->name)
@section('page_title', 'Tiendas')
@section('page_subtitle', $store->name)
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('stores.index') }}">Tiendas</a></li>
    <li class="breadcrumb-item active">{{ $store->name }}</li>
@endsection

@section('content')
    @include('stores.partials.header')

    <div class="card card-tabs-toolbar">
        <div class="card-header">
            @include('stores.partials.detail-tabs')
            @include('partials.table-toolbar', ['table' => 'products-table'])
        </div>
        <div class="card-body">
            @can('products.store')
                <div class="mb-3">
                    <a href="{{ route('stores.products.create', $store) }}" class="btn btn-primary btn-sm"><i class="fas fa-plus"></i> Agregar producto</a>
                </div>
            @endcan
            {!! $dataTable->table() !!}
        </div>
    </div>

    @can('products.stock')
        @include('stores.partials.stock-modal')
    @endcan
@endsection

@push('scripts')
    {!! $dataTable->scripts() !!}
@endpush
