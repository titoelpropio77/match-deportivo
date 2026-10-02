@extends('layouts.admin')

@section('title', 'Tiendas')
@section('page_title', 'Tiendas')
@section('page_subtitle', 'Tiendas de los centros deportivos')
@section('breadcrumb')
    <li class="breadcrumb-item active">Tiendas</li>
@endsection

@section('content')
    <div class="card card-tabs-toolbar">
        <div class="card-header">
            @include('stores.partials.tabs')
            @include('partials.table-toolbar', ['table' => 'stores-table'])
        </div>
        <div class="card-body">
            {!! $dataTable->table() !!}
        </div>
    </div>
@endsection

@push('scripts')
    {!! $dataTable->scripts() !!}
@endpush
