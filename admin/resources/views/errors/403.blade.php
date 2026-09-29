@extends('layouts.admin')

@section('title', 'Acceso denegado')
@section('page_title', 'Acceso denegado')

@section('content')
    <div class="error-page mt-5">
        <h2 class="headline text-warning">403</h2>
        <div class="error-content">
            <h3><i class="fas fa-exclamation-triangle text-warning"></i> No tienes permiso para ver esta página.</h3>
            <p>{{ $exception->getMessage() && ! str_contains($exception->getMessage(), 'User does not have') ? $exception->getMessage() : 'Pide a un administrador que te asigne el permiso necesario.' }}</p>
            <a href="{{ url()->previous() }}" class="btn btn-outline-primary"><i class="fas fa-arrow-left"></i> Volver</a>
        </div>
    </div>
@endsection
