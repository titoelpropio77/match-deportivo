@extends('settings.layout')

@section('title', 'Roles')
@section('toolbar_table', 'roles-table')
@section('breadcrumb')
    <li class="breadcrumb-item active">Lista de roles</li>
@endsection

@section('settings_content')
    {!! $dataTable->table() !!}
@endsection

@push('scripts')
    {!! $dataTable->scripts() !!}
@endpush
