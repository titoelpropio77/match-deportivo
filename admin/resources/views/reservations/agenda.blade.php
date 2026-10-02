@extends('layouts.admin')

@section('title', 'Agenda de reservas')
@section('page_title', 'Reservas')
@section('page_subtitle', 'Agenda del día')
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('reservations.index') }}">Reservas</a></li>
    <li class="breadcrumb-item active">Agenda</li>
@endsection

@php
    $query = fn (array $changes) => route('reservations.agenda', array_merge(['court_id' => $court?->id, 'date' => $date->toDateString()], $changes));
    // Reservations already drawn with a rowspan, keyed by id.
    $drawn = [];
@endphp

@section('content')
    <div class="card card-tabs-toolbar">
        <div class="card-header">
            @include('reservations.partials.tabs')
        </div>
        <div class="card-body">
            @if ($courts->isEmpty())
                <p class="text-muted mb-0">No tienes centros deportivos asignados.</p>
            @else
                <form method="GET" action="{{ route('reservations.agenda') }}" class="form-row align-items-end mb-3">
                    <div class="col-md-4 form-group mb-2">
                        <label for="court_id" class="small mb-1">Centro deportivo</label>
                        <select id="court_id" name="court_id" class="form-control" onchange="this.form.submit()">
                            @foreach ($courts as $option)
                                <option value="{{ $option->id }}" @selected($court?->id === $option->id)>{{ $option->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-3 form-group mb-2">
                        <label for="date" class="small mb-1">Fecha</label>
                        <input type="date" id="date" name="date" class="form-control" value="{{ $date->toDateString() }}" onchange="this.form.submit()">
                    </div>
                    <div class="col-md-5 form-group mb-2">
                        <div class="btn-group">
                            <a href="{{ $query(['date' => $date->copy()->subDay()->toDateString()]) }}" class="btn btn-default"><i class="fas fa-chevron-left"></i></a>
                            <a href="{{ $query(['date' => today()->toDateString()]) }}" class="btn btn-default">Hoy</a>
                            <a href="{{ $query(['date' => $date->copy()->addDay()->toDateString()]) }}" class="btn btn-default"><i class="fas fa-chevron-right"></i></a>
                        </div>
                        <span class="ml-2 font-weight-bold">{{ ucfirst($date->locale('es')->isoFormat('dddd D [de] MMMM')) }}</span>
                    </div>
                </form>

                @if ($fields->isEmpty())
                    <p class="text-muted mb-0">Este centro deportivo todavía no tiene canchas físicas.</p>
                @else
                    <div class="mb-2 small">
                        <span class="badge badge-success">Pagada</span>
                        <span class="badge badge-info">Confirmada (paga en el local)</span>
                        <span class="badge badge-warning">Pago pendiente (app)</span>
                        @can('reservations.store')
                            <span class="text-muted ml-2">Haz clic en un horario libre para registrar una reserva.</span>
                        @endcan
                    </div>
                    <div class="table-responsive">
                        <table class="table table-bordered agenda-table mb-0">
                            <thead>
                            <tr>
                                <th style="width: 110px;">Hora</th>
                                @foreach ($fields as $field)
                                    <th>
                                        {{ $field->name }}
                                        <br><small class="text-muted">Bs {{ number_format((float) $field->price_per_hour, 0) }}/h · {{ $field->sports->pluck('name')->implode(', ') }}</small>
                                    </th>
                                @endforeach
                            </tr>
                            </thead>
                            <tbody>
                            @foreach ($hours as $hour)
                                @php $key = $hour['start']->format('H:i'); $past = $hour['start']->lte(now()); @endphp
                                <tr>
                                    <th class="text-nowrap">{{ $key }} – {{ $hour['end']->format('H:i') }}</th>
                                    @foreach ($fields as $field)
                                        @php $reservation = $grid[$key][$field->id] ?? null; @endphp
                                        @if ($reservation && isset($drawn[$reservation->id]))
                                            @continue
                                        @endif
                                        @if ($reservation)
                                            @php
                                                $drawn[$reservation->id] = true;
                                                // Span the hours it covers from this row on (it may have started before opening).
                                                $span = (int) max(1, ceil($hour['start']->diffInMinutes($reservation->endsAt()) / 60));
                                            @endphp
                                            <td rowspan="{{ $span }}" class="agenda-slot agenda-slot--{{ $reservation->statusColor() }}">
                                                <a href="{{ route('reservations.show', $reservation) }}" class="d-block text-reset">
                                                    <strong>{{ $reservation->customerName() }}</strong>
                                                    <br><small>{{ $reservation->sport?->name }} · {{ $reservation->timeRange() }}</small>
                                                    <br><span class="badge badge-{{ $reservation->statusColor() }}">{{ $reservation->statusLabel() }}</span>
                                                </a>
                                            </td>
                                        @elseif ($past)
                                            <td class="agenda-slot agenda-slot--past"></td>
                                        @else
                                            <td class="agenda-slot agenda-slot--free">
                                                @can('reservations.store')
                                                    <a href="{{ route('reservations.create', ['court_field_id' => $field->id, 'date' => $date->toDateString(), 'start_time' => $key]) }}"
                                                       class="d-block text-muted small" title="Registrar reserva">
                                                        <i class="fas fa-plus"></i> Libre
                                                    </a>
                                                @else
                                                    <span class="text-muted small">Libre</span>
                                                @endcan
                                            </td>
                                        @endif
                                    @endforeach
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            @endif
        </div>
    </div>
@endsection
