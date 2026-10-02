@extends('layouts.admin')

@section('title', $court->name)
@section('page_title', 'Centros deportivos')
@section('page_subtitle', $court->name)
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('courts.index') }}">Centros deportivos</a></li>
    <li class="breadcrumb-item active">Detalle</li>
@endsection

@section('content')
    <div class="row">
        <div class="col-lg-5">
            <div class="card card-primary card-outline">
                <div class="card-header">
                    <h3 class="card-title">{{ $court->name }}</h3>
                    <div class="card-tools">
                        @can('reservations.index')
                            <a href="{{ route('reservations.agenda', ['court_id' => $court->id]) }}" class="btn btn-sm btn-default"><i class="far fa-calendar-alt"></i> Agenda</a>
                            <a href="{{ route('reservations.index', ['court_id' => $court->id]) }}" class="btn btn-sm btn-default"><i class="fas fa-calendar-check"></i> Reservas</a>
                        @endcan
                        @can('courts.update')
                            <a href="{{ route('courts.edit', $court) }}" class="btn btn-sm btn-primary"><i class="fas fa-edit"></i> Editar</a>
                        @endcan
                    </div>
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
            <div class="card card-info card-outline">
                <div class="card-header"><h3 class="card-title">Galería de fotos</h3></div>
                <div class="card-body">
                    @if ($court->photos->isEmpty())
                        <span class="text-muted">Este centro deportivo aún no tiene fotos.</span>
                    @else
                        <div class="d-flex flex-wrap" style="gap: .75rem;">
                            @foreach ($court->photos as $photo)
                                <a href="{{ $photo->src }}" target="_blank" rel="noopener">
                                    <img src="{{ $photo->src }}" alt="Foto de {{ $court->name }}" loading="lazy"
                                         class="rounded" style="width: 140px; height: 105px; object-fit: cover;">
                                </a>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>
            <div class="card card-success card-outline">
                <div class="card-header"><h3 class="card-title">Canchas físicas</h3></div>
                <div class="card-body p-0">
                    @include('courts.partials.fields-table', ['editable' => false])
                </div>
            </div>
            <div class="card card-info card-outline">
                <div class="card-header"><h3 class="card-title"><i class="fas fa-volleyball-ball mr-1"></i> Artículos en alquiler</h3></div>
                <div class="card-body p-0">
                    @include('courts.partials.rental-items-table', ['editable' => false])
                </div>
            </div>
            <div class="card card-warning card-outline">
                <div class="card-header">
                    <h3 class="card-title"><i class="fas fa-glass-cheers mr-1"></i> Espacios para eventos</h3>
                    @can('event_reservations.index')
                        <div class="card-tools">
                            <a href="{{ route('event-reservations.index', ['court_id' => $court->id]) }}" class="btn btn-sm btn-default"><i class="fas fa-calendar-check"></i> Reservas de eventos</a>
                        </div>
                    @endcan
                </div>
                <div class="card-body p-0">
                    @include('courts.partials.event-spaces-table', ['editable' => false])
                </div>
            </div>
            @can('stores.index')
                <div class="card card-success card-outline">
                    <div class="card-header">
                        <h3 class="card-title"><i class="fas fa-store mr-1"></i> Tiendas</h3>
                        @can('stores.store')
                            <div class="card-tools">
                                <a href="{{ route('stores.create', ['court_id' => $court->id]) }}" class="btn btn-sm btn-success"><i class="fas fa-plus"></i> Crear tienda</a>
                            </div>
                        @endcan
                    </div>
                    <div class="card-body p-0">
                        <table class="table table-sm mb-0">
                            <tbody>
                                @forelse ($court->stores as $store)
                                    <tr>
                                        <td class="pl-3"><a href="{{ route('stores.show', $store) }}">{{ $store->name }}</a></td>
                                        <td>
                                            @foreach ($store->categories as $category)
                                                <span class="badge badge-info">{{ $category->name }}</span>
                                            @endforeach
                                        </td>
                                        <td class="text-muted text-nowrap">{{ $store->products_count }} productos</td>
                                        <td class="pr-3 text-right">
                                            @if ($store->is_active)
                                                <span class="badge badge-success">Activa</span>
                                            @else
                                                <span class="badge badge-secondary">Inactiva</span>
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr><td class="text-muted p-3">Este centro todavía no tiene tiendas.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            @endcan
            @include('courts.partials.managers', ['editable' => false])
        </div>
    </div>
@endsection
