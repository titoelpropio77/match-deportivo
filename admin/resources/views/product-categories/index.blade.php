@extends('layouts.admin')

@section('title', 'Categorías de productos')
@section('page_title', 'Categorías de productos')
@section('page_subtitle', 'Lo que venden las tiendas de los centros deportivos')
@section('breadcrumb')
    <li class="breadcrumb-item active">Categorías de productos</li>
@endsection

@section('content')
    <div class="card card-tabs-toolbar">
        <div class="card-header">
            @include('product-categories.partials.tabs')
            @include('partials.table-toolbar', ['table' => 'product-categories-table'])
        </div>
        <div class="card-body">
            {!! $dataTable->table() !!}
        </div>
    </div>
@endsection

@push('scripts')
    {!! $dataTable->scripts() !!}
@endpush
