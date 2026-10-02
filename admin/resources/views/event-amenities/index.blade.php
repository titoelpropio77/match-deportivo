@extends('layouts.admin')

@section('title', 'Servicios de espacios para eventos')
@section('page_title', 'Servicios de espacios para eventos')
@section('page_subtitle', 'Lo que puede incluir cada espacio para eventos')
@section('breadcrumb')
    <li class="breadcrumb-item active">Servicios de espacios para eventos</li>
@endsection

@section('content')
    <div class="card card-tabs-toolbar">
        <div class="card-header">
            @include('event-amenities.partials.tabs')
            @include('partials.table-toolbar', ['table' => 'event-amenities-table'])
        </div>
        <div class="card-body">
            {!! $dataTable->table() !!}
        </div>
    </div>
@endsection

@push('scripts')
    {!! $dataTable->scripts() !!}
@endpush
