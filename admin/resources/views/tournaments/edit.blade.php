@extends('layouts.admin')

@section('title', 'Editar torneo')
@section('page_title', 'Torneos')
@section('page_subtitle', 'Editar '.$tournament->name)
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('tournaments.index') }}">Torneos</a></li>
    <li class="breadcrumb-item"><a href="{{ route('tournaments.show', $tournament) }}">{{ $tournament->name }}</a></li>
    <li class="breadcrumb-item active">Editar</li>
@endsection

@section('content')
    <div class="card card-primary card-outline">
        <form method="POST" action="{{ route('tournaments.update', $tournament) }}" enctype="multipart/form-data">
            @method('PUT')
            <div class="card-body">@include('tournaments._form')</div>
            <div class="card-footer text-right">
                <a href="{{ route('tournaments.show', $tournament) }}" class="btn btn-default">Cancelar</a>
                <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Guardar cambios</button>
            </div>
        </form>
    </div>
@endsection
