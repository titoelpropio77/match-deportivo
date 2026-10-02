@extends('layouts.admin')

@section('title', 'Usuarios')
@section('page_title', 'Usuarios')
@section('page_subtitle', 'Lista de usuarios')
@section('breadcrumb')
    <li class="breadcrumb-item active">Usuarios</li>
@endsection

@section('content')
    <div class="card card-tabs-toolbar">
        <div class="card-header">
            <ul class="nav nav-tabs">
                <li class="nav-item"><a class="nav-link active" href="{{ route('users.index') }}"><i class="fas fa-list"></i> Lista de usuarios</a></li>
                @can('users.store')
                    <li class="nav-item"><a class="nav-link" href="{{ route('users.create') }}"><i class="fas fa-plus"></i> Crear usuario</a></li>
                @endcan
            </ul>
            @include('partials.table-toolbar', ['table' => 'users-table'])
        </div>
        <div class="card-body">
            {!! $dataTable->table() !!}
        </div>
    </div>
@endsection

@push('scripts')
    {!! $dataTable->scripts() !!}
@endpush
