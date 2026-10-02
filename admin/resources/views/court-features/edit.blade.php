@extends('layouts.admin')

@section('title', 'Editar característica')
@section('page_title', 'Características de canchas')
@section('page_subtitle', 'Editar característica')
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('court-features.index') }}">Características de canchas</a></li>
    <li class="breadcrumb-item active">Editar</li>
@endsection

@section('content')
    <div class="card card-tabs-toolbar">
        <div class="card-header">@include('court-features.partials.tabs')</div>
        <form method="POST" action="{{ route('court-features.update', $feature) }}">
            @method('PUT')
            <div class="card-body">@include('court-features._form')</div>
            <div class="card-footer text-right">
                <a href="{{ route('court-features.index') }}" class="btn btn-default">Cancelar</a>
                <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Guardar cambios</button>
            </div>
        </form>
    </div>
@endsection
