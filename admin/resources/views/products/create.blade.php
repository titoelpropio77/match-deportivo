@extends('layouts.admin')

@section('title', 'Agregar producto')
@section('page_title', 'Tiendas')
@section('page_subtitle', $store->name.' · Agregar producto')
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('stores.index') }}">Tiendas</a></li>
    <li class="breadcrumb-item"><a href="{{ route('stores.show', $store) }}">{{ $store->name }}</a></li>
    <li class="breadcrumb-item active">Agregar producto</li>
@endsection

@section('content')
    <div class="card card-primary card-outline">
        <form method="POST" action="{{ route('stores.products.store', $store) }}" enctype="multipart/form-data">
            <div class="card-body">@include('products._form', ['held' => 0])</div>
            <div class="card-footer text-right">
                <a href="{{ route('stores.show', $store) }}" class="btn btn-default">Cancelar</a>
                <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Agregar producto</button>
            </div>
        </form>
    </div>
@endsection
