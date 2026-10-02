@extends('layouts.admin')

@section('title', 'Editar categoría')
@section('page_title', 'Categorías de productos')
@section('page_subtitle', 'Editar categoría')
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('product-categories.index') }}">Categorías de productos</a></li>
    <li class="breadcrumb-item active">Editar</li>
@endsection

@section('content')
    <div class="card card-tabs-toolbar">
        <div class="card-header">@include('product-categories.partials.tabs')</div>
        <form method="POST" action="{{ route('product-categories.update', $category) }}">
            @method('PUT')
            <div class="card-body">@include('product-categories._form')</div>
            <div class="card-footer text-right">
                <a href="{{ route('product-categories.index') }}" class="btn btn-default">Cancelar</a>
                <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Guardar cambios</button>
            </div>
        </form>
    </div>
@endsection
