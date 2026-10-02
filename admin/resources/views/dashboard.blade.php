@extends('layouts.admin')

@section('title', 'Dashboard')
@section('page_title', 'Dashboard')
@section('page_subtitle', 'Resumen general')

@section('content')
    <div class="row">
        @php
            $boxes = [
                ['label' => 'Usuarios', 'value' => $stats['users'], 'icon' => 'fa-users', 'color' => 'info', 'route' => 'users.index', 'can' => 'users.index'],
                ['label' => auth()->user()->can('courts.view_all') ? 'Centros deportivos' : 'Mis centros deportivos', 'value' => $stats['courts'], 'icon' => 'fa-map-marked-alt', 'color' => 'success', 'route' => 'courts.index', 'can' => 'courts.index'],
                ['label' => 'Canchas físicas', 'value' => $stats['fields'], 'icon' => 'fa-border-all', 'color' => 'primary', 'route' => 'courts.index', 'can' => 'courts.index'],
                ['label' => 'Partidos abiertos', 'value' => $stats['openMatches'], 'icon' => 'fa-futbol', 'color' => 'warning', 'route' => null, 'can' => null],
                ['label' => 'Reservas para hoy', 'value' => $stats['reservationsToday'], 'icon' => 'fa-calendar-check', 'color' => 'danger', 'route' => 'reservations.agenda', 'can' => 'reservations.index'],
                ['label' => 'Ingresos del mes', 'value' => $stats['monthIncome'] === null ? null : 'Bs '.number_format($stats['monthIncome'], 0), 'icon' => 'fa-coins', 'color' => 'secondary', 'route' => 'reservations.index', 'can' => 'reservations.index'],
            ];
        @endphp
        @foreach (array_filter($boxes, fn ($box) => $box['value'] !== null) as $box)
            <div class="col-lg col-md-4 col-sm-6">
                <div class="small-box bg-{{ $box['color'] }}">
                    <div class="inner">
                        <h3>{{ $box['value'] }}</h3>
                        <p>{{ $box['label'] }}</p>
                    </div>
                    <div class="icon"><i class="fas {{ $box['icon'] }}"></i></div>
                    @if ($box['route'] && auth()->user()->can($box['can']))
                        <a href="{{ route($box['route']) }}" class="small-box-footer">Ver más <i class="fas fa-arrow-circle-right"></i></a>
                    @else
                        <span class="small-box-footer">&nbsp;</span>
                    @endif
                </div>
            </div>
        @endforeach
    </div>

    @if ($stats['pendingRefunds'] > 0)
        <div class="alert alert-warning">
            <i class="fas fa-hand-holding-usd mr-1"></i>
            Hay {{ $stats['pendingRefunds'] }} {{ $stats['pendingRefunds'] === 1 ? 'reserva anulada' : 'reservas anuladas' }} con devolución pendiente.
            <a href="{{ route('reservations.index', ['status' => 'refund_pending']) }}" class="alert-link">Ver</a>
        </div>
    @endif

    @if ($stats['ordersToDeliver'] > 0)
        <div class="alert alert-info">
            <i class="fas fa-box-open mr-1"></i>
            {{ $stats['ordersToDeliver'] === 1 ? 'Hay 1 compra pagada' : "Hay {$stats['ordersToDeliver']} compras pagadas" }} en tus tiendas esperando ser entregada{{ $stats['ordersToDeliver'] === 1 ? '' : 's' }}.
            <a href="{{ route('store-orders.index', ['status' => 'ready']) }}" class="alert-link">Ver</a>
        </div>
    @endif

    @can('reservations.index')
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Próximas reservas</h3>
                <div class="card-tools"><a href="{{ route('reservations.index', ['status' => 'active']) }}" class="btn btn-sm btn-default">Ver todas</a></div>
            </div>
            <div class="card-body p-0">
                <table class="table table-striped mb-0">
                    <thead><tr><th>Fecha</th><th>Horario</th><th>Centro / cancha</th><th>Cliente</th><th>Estado</th><th></th></tr></thead>
                    <tbody>
                    @forelse ($upcomingReservations as $reservation)
                        <tr>
                            <td>{{ $reservation->reserved_on->isToday() ? 'Hoy' : $reservation->reserved_on->format('d/m/Y') }}</td>
                            <td>{{ $reservation->timeRange() }}</td>
                            <td>{{ $reservation->field->court->name }} <small class="text-muted">· {{ $reservation->field->name }}</small></td>
                            <td>{{ $reservation->customerName() }}</td>
                            <td>@include('reservations.partials.status')</td>
                            <td class="text-right"><a href="{{ route('reservations.show', $reservation) }}" class="btn btn-link btn-sm"><i class="fas fa-eye"></i></a></td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="text-center text-muted">No hay reservas próximas.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    @endcan

    @can('users.index')
        <div class="card">
            <div class="card-header"><h3 class="card-title">Últimos usuarios registrados</h3></div>
            <div class="card-body p-0">
                <table class="table table-striped mb-0">
                    <thead><tr><th>Nombre</th><th>Email</th><th>Roles</th><th>Registrado</th></tr></thead>
                    <tbody>
                    @forelse ($latestUsers as $user)
                        <tr>
                            <td>{{ $user->name }}</td>
                            <td>{{ $user->email }}</td>
                            <td>@include('users.partials.roles', ['user' => $user])</td>
                            <td>{{ $user->created_at?->diffForHumans() }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="text-center text-muted">Sin usuarios.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    @endcan
@endsection
