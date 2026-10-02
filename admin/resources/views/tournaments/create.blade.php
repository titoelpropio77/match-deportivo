@extends('layouts.admin')

@section('title', 'Crear torneo')
@section('page_title', 'Torneos')
@section('page_subtitle', 'Crear torneo')
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('tournaments.index') }}">Torneos</a></li>
    <li class="breadcrumb-item active">Crear</li>
@endsection

@section('content')
    <div class="card card-tabs-toolbar">
        <div class="card-header">@include('tournaments.partials.tabs')</div>
        <form method="POST" action="{{ route('tournaments.store') }}" enctype="multipart/form-data">
            <div class="card-body">@include('tournaments._form')</div>
            <div class="card-footer text-right">
                <a href="{{ route('tournaments.index') }}" class="btn btn-default">Cancelar</a>
                <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Crear torneo</button>
            </div>
        </form>
    </div>
@endsection
