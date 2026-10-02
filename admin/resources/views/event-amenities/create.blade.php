@extends('layouts.admin')

@section('title', 'Crear servicio')
@section('page_title', 'Servicios de espacios para eventos')
@section('page_subtitle', 'Crear servicio')
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('event-amenities.index') }}">Servicios de espacios para eventos</a></li>
    <li class="breadcrumb-item active">Crear</li>
@endsection

@section('content')
    <div class="card card-tabs-toolbar">
        <div class="card-header">@include('event-amenities.partials.tabs')</div>
        <form method="POST" action="{{ route('event-amenities.store') }}">
            <div class="card-body">@include('event-amenities._form')</div>
            <div class="card-footer text-right">
                <a href="{{ route('event-amenities.index') }}" class="btn btn-default">Cancelar</a>
                <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Crear servicio</button>
            </div>
        </form>
    </div>
@endsection
