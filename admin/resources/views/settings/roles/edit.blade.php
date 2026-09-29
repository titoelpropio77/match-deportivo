@extends('settings.layout')

@section('title', 'Editar rol')
@section('extra_tab')<i class="fas fa-edit"></i> Editar rol {{ $role->name }}@endsection
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('settings.roles.index') }}">Roles</a></li>
    <li class="breadcrumb-item active">Editar</li>
@endsection

@section('settings_content')
    <form method="POST" action="{{ route('settings.roles.update', $role) }}">
        @method('PUT')
        @include('settings.roles._form')
        <div class="text-right">
            <a href="{{ route('settings.roles.index') }}" class="btn btn-default">Cancelar</a>
            <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Guardar cambios</button>
        </div>
    </form>
@endsection
