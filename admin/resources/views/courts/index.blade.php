@extends('layouts.admin')

@section('title', 'Centros deportivos')
@section('page_title', 'Centros deportivos')
@section('page_subtitle', 'Lista de centros deportivos')
@section('breadcrumb')
    <li class="breadcrumb-item active">Centros deportivos</li>
@endsection

@section('content')
    <div class="card card-tabs-toolbar">
        <div class="card-header">
            <ul class="nav nav-tabs">
                <li class="nav-item"><a class="nav-link active" href="{{ route('courts.index') }}"><i class="fas fa-list"></i> Lista de centros deportivos</a></li>
                @can('courts.store')
                    <li class="nav-item"><a class="nav-link" href="{{ route('courts.create') }}"><i class="fas fa-plus"></i> Crear centro deportivo</a></li>
                @endcan
            </ul>
            @include('partials.table-toolbar', ['table' => 'courts-table'])
        </div>
        <div class="card-body">
            {!! $dataTable->table() !!}
        </div>
    </div>
@endsection

@push('scripts')
    {!! $dataTable->scripts() !!}
@endpush
