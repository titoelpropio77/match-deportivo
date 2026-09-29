@extends('layouts.admin')

@section('title', 'Crear usuario')
@section('page_title', 'Usuarios')
@section('page_subtitle', 'Crear usuario')
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('users.index') }}">Usuarios</a></li>
    <li class="breadcrumb-item active">Crear</li>
@endsection

@section('content')
    <div class="card card-tabs-toolbar">
        <div class="card-header">
            <ul class="nav nav-tabs">
                @can('users.index')
                    <li class="nav-item"><a class="nav-link" href="{{ route('users.index') }}"><i class="fas fa-list"></i> Lista de usuarios</a></li>
                @endcan
                <li class="nav-item"><a class="nav-link active" href="{{ route('users.create') }}"><i class="fas fa-plus"></i> Crear usuario</a></li>
            </ul>
        </div>
        <form method="POST" action="{{ route('users.store') }}">
            <div class="card-body">
                @include('users._form')
            </div>
            <div class="card-footer text-right">
                <a href="{{ route('users.index') }}" class="btn btn-default">Cancelar</a>
                <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Guardar</button>
            </div>
        </form>
    </div>
@endsection
