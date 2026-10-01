@extends('layouts.admin')

@section('title', 'Centros deportivos')
@section('page_title', 'Centros deportivos')
@section('page_subtitle', 'Lista de centros deportivos')
@section('breadcrumb')
    <li class="breadcrumb-item active">Centros deportivos</li>
@endsection

@section('content')
    <div class="card card-tabs-toolbar">
        <div class="card-header">
            <ul class="nav nav-tabs">
                <li class="nav-item"><a class="nav-link active" href="{{ route('courts.index') }}"><i class="fas fa-list"></i> Lista de centros deportivos</a></li>
                @can('courts.store')
                    <li class="nav-item"><a class="nav-link" href="{{ route('courts.create') }}"><i class="fas fa-plus"></i> Crear centro deportivo</a></li>
                @endcan
            </ul>
            @include('partials.table-toolbar', ['table' => 'courts-table'])
        </div>
        <div class="card-body">
            <table id="courts-table" class="table table-hover w-100">
                <thead>
                <tr>
                    <th>Id</th>
                    <th>Nombre</th>
                    <th>Ciudad</th>
                    <th>Dirección</th>
                    <th>Partner</th>
                    <th>Deportes</th>
                    <th>Canchas físicas</th>
                    <th>Horario</th>
                    <th>Actualizado</th>
                    <th class="no-export no-colvis">Acción</th>
                </tr>
                </thead>
            </table>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        AdminTable.init('#courts-table', {
            processing: true,
            serverSide: true,
            ajax: @json(route('courts.data')),
            order: [[1, 'asc']],
            columns: [
                { data: 'id', name: 'id' },
                { data: 'name', name: 'name' },
                { data: 'city', name: 'city', orderable: false },
                { data: 'address', name: 'address' },
                { data: 'owner', name: 'owner', orderable: false },
                { data: 'sports', name: 'sports', orderable: false, searchable: false },
                { data: 'fields_count', name: 'fields_count', searchable: false, className: 'text-center' },
                { data: 'schedule', name: 'schedule', orderable: false, searchable: false },
                { data: 'updated_at', name: 'updated_at', searchable: false },
                { data: 'action', name: 'action', orderable: false, searchable: false },
            ],
        });
    </script>
@endpush
