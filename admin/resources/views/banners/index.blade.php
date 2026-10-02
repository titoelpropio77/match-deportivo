@extends('layouts.admin')

@section('title', 'Banners')
@section('page_title', 'Banners de la app')
@section('page_subtitle', 'Carrusel de la pantalla de inicio')
@section('breadcrumb')
    <li class="breadcrumb-item active">Banners</li>
@endsection

@section('content')
    <div class="card card-tabs-toolbar">
        <div class="card-header">
            @include('banners.partials.tabs')
            @include('partials.table-toolbar', ['table' => 'banners-table'])
        </div>
        <div class="card-body">
            <p class="text-muted small mb-3"><i class="fas fa-info-circle"></i> La app muestra los banners activos y vigentes, ordenados por el campo <strong>Orden</strong>. Los que llevan a un torneo en borrador o a un registro eliminado se ocultan solos.</p>
            {!! $dataTable->table() !!}
        </div>
    </div>
@endsection

@push('scripts')
    {!! $dataTable->scripts() !!}
@endpush
