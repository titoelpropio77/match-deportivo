@extends('layouts.admin')

@section('title', 'Crear categoría')
@section('page_title', 'Categorías de productos')
@section('page_subtitle', 'Crear categoría')
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('product-categories.index') }}">Categorías de productos</a></li>
    <li class="breadcrumb-item active">Crear</li>
@endsection

@section('content')
    <div class="card card-tabs-toolbar">
        <div class="card-header">@include('product-categories.partials.tabs')</div>
        <form method="POST" action="{{ route('product-categories.store') }}">
            <div class="card-body">@include('product-categories._form')</div>
            <div class="card-footer text-right">
                <a href="{{ route('product-categories.index') }}" class="btn btn-default">Cancelar</a>
                <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Crear categoría</button>
            </div>
        </form>
    </div>
@endsection
