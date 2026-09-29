@extends('layouts.admin')

@section('title', $court->name)
@section('page_title', 'Canchas')
@section('page_subtitle', $court->name)
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('courts.index') }}">Canchas</a></li>
    <li class="breadcrumb-item active">Detalle</li>
@endsection

@section('content')
    <div class="row">
        <div class="col-lg-5">
            <div class="card card-primary card-outline">
                <div class="card-header">
                    <h3 class="card-title">{{ $court->name }}</h3>
                    @can('courts.update')
                        <div class="card-tools"><a href="{{ route('courts.edit', $court) }}" class="btn btn-sm btn-primary"><i class="fas fa-edit"></i> Editar</a></div>
                    @endcan
                </div>
                <div class="card-body">
                    <dl class="row mb-0">
                        <dt class="col-sm-5">Ciudad</dt><dd class="col-sm-7">{{ $court->city ? $court->city->name.' ('.$court->city->department.')' : '—' }}</dd>
                        <dt class="col-sm-5">Dirección</dt><dd class="col-sm-7">{{ $court->address }}</dd>
                        <dt class="col-sm-5">Ubicación</dt>
                        <dd class="col-sm-7">
                            <a href="https://www.google.com/maps?q={{ $court->latitude }},{{ $court->longitude }}" target="_blank" rel="noopener">
                                {{ $court->latitude }}, {{ $court->longitude }} <i class="fas fa-external-link-alt small"></i>
                            </a>
                        </dd>
                        <dt class="col-sm-5">Horario</dt><dd class="col-sm-7">{{ substr($court->opening_time, 0, 5) }} – {{ substr($court->closing_time, 0, 5) }}</dd>
                        <dt class="col-sm-5">Partner</dt><dd class="col-sm-7">{{ $court->owner?->name ?? '—' }}</dd>
                        <dt class="col-sm-5">Deportes</dt>
                        <dd class="col-sm-7">
                            @forelse ($court->sports as $sport)
                                <span class="badge badge-info">{{ $sport->name }}</span>
                            @empty
                                <span class="text-muted">—</span>
                            @endforelse
                        </dd>
                        <dt class="col-sm-5">Partidos registrados</dt><dd class="col-sm-7">{{ $court->matches_count }}</dd>
                    </dl>
                    <div class="row mt-3">
                        @include('courts.partials.map-picker', ['editable' => false])
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-7">
            <div class="card card-success card-outline">
                <div class="card-header"><h3 class="card-title">Canchas físicas</h3></div>
                <div class="card-body p-0">
                    @include('courts.partials.fields-table', ['editable' => false])
                </div>
            </div>
        </div>
    </div>
@endsection
