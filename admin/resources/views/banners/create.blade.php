@extends('layouts.admin')

@section('title', 'Crear banner')
@section('page_title', 'Banners de la app')
@section('page_subtitle', 'Crear banner')
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('banners.index') }}">Banners</a></li>
    <li class="breadcrumb-item active">Crear</li>
@endsection

@section('content')
    <div class="card card-tabs-toolbar">
        <div class="card-header">@include('banners.partials.tabs')</div>
        <form method="POST" action="{{ route('banners.store') }}" enctype="multipart/form-data">

            <div class="card-body">@include('banners._form')</div>
            <div class="card-footer text-right">
                <a href="{{ route('banners.index') }}" class="btn btn-default">Cancelar</a>
                <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Crear banner</button>
            </div>
        </form>
    </div>
@endsection
