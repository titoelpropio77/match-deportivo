@extends('settings.layout')

@section('title', 'Crear permiso')
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('settings.permissions.index') }}">Permisos</a></li>
    <li class="breadcrumb-item active">Crear</li>
@endsection

@section('settings_content')
    <form method="POST" action="{{ route('settings.permissions.store') }}">
        @include('settings.permissions._form')
        <div class="text-right">
            <a href="{{ route('settings.permissions.index') }}" class="btn btn-default">Cancelar</a>
            <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Guardar</button>
        </div>
    </form>
@endsection
