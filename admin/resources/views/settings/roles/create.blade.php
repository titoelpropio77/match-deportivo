@extends('settings.layout')

@section('title', 'Crear rol')
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('settings.roles.index') }}">Roles</a></li>
    <li class="breadcrumb-item active">Crear</li>
@endsection

@section('settings_content')
    <form method="POST" action="{{ route('settings.roles.store') }}">
        @include('settings.roles._form')
        <div class="text-right">
            <a href="{{ route('settings.roles.index') }}" class="btn btn-default">Cancelar</a>
            <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Guardar</button>
        </div>
    </form>
@endsection
