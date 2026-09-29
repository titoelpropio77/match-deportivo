@extends('settings.layout')

@section('title', 'Editar permiso')
@section('extra_tab')<i class="fas fa-edit"></i> Editar permiso@endsection
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('settings.permissions.index') }}">Permisos</a></li>
    <li class="breadcrumb-item active">Editar</li>
@endsection

@section('settings_content')
    <div class="alert alert-warning"><i class="fas fa-exclamation-triangle"></i> Si renombras un permiso usado por el código (rutas o menú), esa pantalla quedará accesible solo para superadmin.</div>
    <form method="POST" action="{{ route('settings.permissions.update', $permission) }}">
        @method('PUT')
        @include('settings.permissions._form')
        <div class="text-right">
            <a href="{{ route('settings.permissions.index') }}" class="btn btn-default">Cancelar</a>
            <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Guardar cambios</button>
        </div>
    </form>
@endsection
