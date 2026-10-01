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
                ['label' => 'Reservas activas', 'value' => $stats['reservations'], 'icon' => 'fa-calendar-check', 'color' => 'danger', 'route' => null, 'can' => null],
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
