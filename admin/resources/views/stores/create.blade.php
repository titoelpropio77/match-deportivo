@extends('layouts.admin')

@section('title', 'Crear tienda')
@section('page_title', 'Tiendas')
@section('page_subtitle', 'Crear tienda')
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('stores.index') }}">Tiendas</a></li>
    <li class="breadcrumb-item active">Crear</li>
@endsection

@section('content')
    <div class="card card-tabs-toolbar">
        <div class="card-header">@include('stores.partials.tabs')</div>
        <form method="POST" action="{{ route('stores.store') }}" enctype="multipart/form-data">
            <div class="card-body">@include('stores._form')</div>
            <div class="card-footer text-right">
                <a href="{{ route('stores.index') }}" class="btn btn-default">Cancelar</a>
                <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Crear tienda</button>
            </div>
        </form>
    </div>
@endsection
