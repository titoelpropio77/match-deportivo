@extends('layouts.admin')

@section('title', 'Editar producto')
@section('page_title', 'Tiendas')
@section('page_subtitle', $store->name.' · '.$product->name)
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('stores.index') }}">Tiendas</a></li>
    <li class="breadcrumb-item"><a href="{{ route('stores.show', $store) }}">{{ $store->name }}</a></li>
    <li class="breadcrumb-item active">Editar producto</li>
@endsection

@section('content')
    <div class="card card-primary card-outline">
        <form method="POST" action="{{ route('stores.products.update', [$store, $product]) }}" enctype="multipart/form-data">
            @method('PUT')
            <div class="card-body">@include('products._form')</div>
            <div class="card-footer text-right">
                <a href="{{ route('stores.show', $store) }}" class="btn btn-default">Cancelar</a>
                <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Guardar cambios</button>
            </div>
        </form>
    </div>
@endsection
