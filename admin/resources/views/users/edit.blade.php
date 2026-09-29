@extends('layouts.admin')

@section('title', 'Editar usuario')
@section('page_title', 'Usuarios')
@section('page_subtitle', 'Editar '.$user->name)
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('users.index') }}">Usuarios</a></li>
    <li class="breadcrumb-item active">Editar</li>
@endsection

@section('content')
    <div class="card card-primary card-outline">
        <form method="POST" action="{{ route('users.update', $user) }}">
            @method('PUT')
            <div class="card-body">
                @include('users._form')
            </div>
            <div class="card-footer text-right">
                <a href="{{ route('users.index') }}" class="btn btn-default">Cancelar</a>
                <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Guardar cambios</button>
            </div>
        </form>
    </div>
@endsection
