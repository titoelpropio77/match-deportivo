@extends('layouts.admin')

@section('title', 'Crear centro deportivo')
@section('page_title', 'Centros deportivos')
@section('page_subtitle', 'Crear centro deportivo')
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('courts.index') }}">Centros deportivos</a></li>
    <li class="breadcrumb-item active">Crear</li>
@endsection

@section('content')
    <div class="card card-tabs-toolbar">
        <div class="card-header">
            <ul class="nav nav-tabs">
                @can('courts.index')
                    <li class="nav-item"><a class="nav-link" href="{{ route('courts.index') }}"><i class="fas fa-list"></i> Lista de centros deportivos</a></li>
                @endcan
                <li class="nav-item"><a class="nav-link active" href="{{ route('courts.create') }}"><i class="fas fa-plus"></i> Crear centro deportivo</a></li>
            </ul>
        </div>
        <form method="POST" action="{{ route('courts.store') }}" enctype="multipart/form-data">
            <div class="card-body">
                @include('courts._form')
            </div>
            <div class="card-footer text-right">
                <a href="{{ route('courts.index') }}" class="btn btn-default">Cancelar</a>
                <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Guardar</button>
            </div>
        </form>
    </div>
@endsection
