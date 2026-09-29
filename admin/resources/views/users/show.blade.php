@extends('layouts.admin')

@section('title', $user->name)
@section('page_title', 'Usuarios')
@section('page_subtitle', $user->name)
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('users.index') }}">Usuarios</a></li>
    <li class="breadcrumb-item active">Detalle</li>
@endsection

@section('content')
    <div class="row">
        <div class="col-md-4">
            <div class="card card-primary card-outline">
                <div class="card-body box-profile text-center">
                    <span class="avatar-circle mb-2" style="width:90px;height:90px;font-size:2.2rem">{{ mb_strtoupper(mb_substr($user->name, 0, 1)) }}</span>
                    <h3 class="profile-username">{{ $user->name }}</h3>
                    <p class="text-muted">{{ $user->nickname ? '@'.$user->nickname : '' }}</p>
                    <p>@include('users.partials.roles')</p>
                    <ul class="list-group list-group-unbordered mb-3 text-left">
                        <li class="list-group-item"><b>Partidos organizados</b> <span class="float-right">{{ $user->organized_matches_count }}</span></li>
                        <li class="list-group-item"><b>Canchas a cargo</b> <span class="float-right">{{ $user->courts_count }}</span></li>
                    </ul>
                    @can('users.update')
                        <a href="{{ route('users.edit', $user) }}" class="btn btn-primary btn-block"><i class="fas fa-edit"></i> Editar</a>
                    @endcan
                </div>
            </div>
        </div>
        <div class="col-md-8">
            <div class="card">
                <div class="card-header"><h3 class="card-title">Datos del usuario</h3></div>
                <div class="card-body">
                    <dl class="row mb-0">
                        <dt class="col-sm-4">Id</dt><dd class="col-sm-8">{{ $user->id }}</dd>
                        <dt class="col-sm-4">Email</dt><dd class="col-sm-8">@include('partials.email', ['email' => $user->email])</dd>
                        <dt class="col-sm-4">Teléfono</dt><dd class="col-sm-8">{{ $user->phone ?? '—' }}</dd>
                        <dt class="col-sm-4">Género</dt><dd class="col-sm-8">{{ \App\Models\User::GENDERS[$user->gender] ?? '—' }}</dd>
                        <dt class="col-sm-4">Posición preferida</dt><dd class="col-sm-8">{{ $user->preferred_position ?? '—' }}</dd>
                        <dt class="col-sm-4">Registrado</dt><dd class="col-sm-8">{{ $user->created_at?->format('d/m/Y H:i') }} ({{ $user->created_at?->diffForHumans() }})</dd>
                        <dt class="col-sm-4">Actualizado</dt><dd class="col-sm-8">{{ $user->updated_at?->diffForHumans() }}</dd>
                        <dt class="col-sm-4">Permisos efectivos</dt>
                        <dd class="col-sm-8">
                            @if ($user->hasRole('superadmin'))
                                <span class="badge badge-danger">todos (superadmin)</span>
                            @else
                                @forelse ($user->getAllPermissions()->sortBy('name') as $permission)
                                    <span class="badge badge-light border">{{ $permission->name }}</span>
                                @empty
                                    <span class="text-muted">ninguno</span>
                                @endforelse
                            @endif
                        </dd>
                    </dl>
                </div>
            </div>
        </div>
    </div>
@endsection
