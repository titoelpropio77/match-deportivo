@extends('layouts.admin')

@section('title', 'Características de canchas')
@section('page_title', 'Características de canchas')
@section('page_subtitle', 'Opciones que se marcan en cada cancha física')
@section('breadcrumb')
    <li class="breadcrumb-item active">Características de canchas</li>
@endsection

@section('content')
    <div class="card card-tabs-toolbar">
        <div class="card-header">
            @include('court-features.partials.tabs')
            @include('partials.table-toolbar', ['table' => 'court-features-table'])
        </div>
        <div class="card-body">
            {!! $dataTable->table() !!}
        </div>
    </div>
@endsection

@push('scripts')
    {!! $dataTable->scripts() !!}
@endpush
