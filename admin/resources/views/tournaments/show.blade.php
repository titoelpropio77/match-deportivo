@extends('layouts.admin')

@php
    $bs = fn ($amount) => 'Bs '.number_format((float) $amount, 2);
    $confirmedCount = $registrations->where('status', 'confirmed')->count();
    $pendingCount = $registrations->filter(fn ($r) => $r->status === 'pending_payment' && ! $r->isExpired())->count();
    $nextStatuses = [
        'draft' => ['open' => ['Abrir inscripciones', 'success', 'fa-door-open']],
        'open' => ['closed' => ['Cerrar inscripciones', 'info', 'fa-door-closed'], 'in_progress' => ['Iniciar torneo', 'primary', 'fa-play']],
        'closed' => ['open' => ['Reabrir inscripciones', 'default', 'fa-door-open'], 'in_progress' => ['Iniciar torneo', 'primary', 'fa-play']],
        'in_progress' => ['finished' => ['Finalizar torneo', 'dark', 'fa-flag-checkered']],
        'finished' => [],
        'cancelled' => ['draft' => ['Volver a borrador', 'default', 'fa-undo']],
    ][$tournament->status] ?? [];
@endphp

@section('title', $tournament->name)
@section('page_title', 'Torneos')
@section('page_subtitle', $tournament->name)
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('tournaments.index') }}">Torneos</a></li>
    <li class="breadcrumb-item active">{{ $tournament->name }}</li>
@endsection

@section('content')
    <div class="card card-primary card-outline">
        <div class="card-body">
            <div class="row">
                @if ($tournament->cover_path)
                    <div class="col-md-3 mb-3">
                        <img src="{{ $tournament->coverUrl() }}" alt="Portada" class="img-fluid rounded">
                    </div>
                @endif
                <div class="col">
                    <h3 class="mb-1">{{ $tournament->name }} <span class="badge badge-{{ $tournament->statusColor() }} align-middle" style="font-size: .9rem;">{{ $tournament->statusLabel() }}</span></h3>
                    <p class="text-muted mb-2">
                        <i class="fas fa-map-marker-alt"></i> {{ $tournament->court->name }} ·
                        {{ $tournament->sport->name }} · {{ \App\Models\Tournament::FORMATS[$tournament->format] ?? $tournament->format }} ·
                        {{ \App\Models\Tournament::GENDERS[$tournament->gender] ?? $tournament->gender }}
                        @if ($tournament->level) · {{ $tournament->level->name }} @endif
                    </p>
                    <p class="mb-2">
                        <i class="far fa-calendar-alt"></i> {{ $tournament->starts_on->format('d/m/Y') }}{{ $tournament->ends_on ? ' – '.$tournament->ends_on->format('d/m/Y') : '' }}
                        · <i class="fas fa-user-clock"></i> Inscripciones hasta {{ $tournament->registration_closes_at->format('d/m/Y H:i') }}
                    </p>
                    <div>
                        @can('tournaments.update')
                            @foreach ($nextStatuses as $status => [$label, $color, $icon])
                                <form method="POST" action="{{ route('tournaments.status', $tournament) }}" class="d-inline">
                                    @csrf
                                    <input type="hidden" name="status" value="{{ $status }}">
                                    <button type="submit" class="btn btn-sm btn-{{ $color }}"><i class="fas {{ $icon }}"></i> {{ $label }}</button>
                                </form>
                            @endforeach
                            @if (! in_array($tournament->status, ['finished', 'cancelled'], true))
                                <form method="POST" action="{{ route('tournaments.status', $tournament) }}" class="d-inline"
                                      data-confirm="¿Cancelar el torneo? Los equipos inscritos lo verán como cancelado.">
                                    @csrf
                                    <input type="hidden" name="status" value="cancelled">
                                    <button type="submit" class="btn btn-sm btn-outline-danger"><i class="fas fa-ban"></i> Cancelar torneo</button>
                                </form>
                            @endif
                            <a href="{{ route('tournaments.edit', $tournament) }}" class="btn btn-sm btn-outline-primary"><i class="fas fa-edit"></i> Editar</a>
                        @endcan
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        @foreach ([
            ['Equipos confirmados', $confirmedCount.' / '.$tournament->max_teams, 'fa-users', 'success'],
            ['Pagos pendientes', $pendingCount, 'fa-hourglass-half', 'warning'],
            ['Inscripción por equipo', (float) $tournament->entry_fee > 0 ? $bs($tournament->entry_fee) : 'Gratis', 'fa-ticket-alt', 'info'],
            ['Recaudado', $bs($collected), 'fa-coins', 'secondary'],
        ] as [$label, $value, $icon, $color])
            <div class="col-lg-3 col-sm-6">
                <div class="info-box">
                    <span class="info-box-icon bg-{{ $color }}"><i class="fas {{ $icon }}"></i></span>
                    <div class="info-box-content">
                        <span class="info-box-text">{{ $label }}</span>
                        <span class="info-box-number">{{ $value }}</span>
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    <div class="card card-primary card-outline card-outline-tabs">
        <div class="card-header p-0 border-bottom-0">
            <ul class="nav nav-tabs" role="tablist">
                <li class="nav-item"><a class="nav-link active" data-toggle="pill" href="#teams" role="tab"><i class="fas fa-users"></i> Equipos inscritos ({{ $registrations->count() }})</a></li>
                <li class="nav-item"><a class="nav-link" data-toggle="pill" href="#fixture" role="tab"><i class="far fa-calendar-alt"></i> Fixture ({{ $games->count() }})</a></li>
                <li class="nav-item"><a class="nav-link" data-toggle="pill" href="#standings" role="tab"><i class="fas fa-list-ol"></i> Tabla de posiciones</a></li>
                <li class="nav-item"><a class="nav-link" data-toggle="pill" href="#info" role="tab"><i class="fas fa-info-circle"></i> Información</a></li>
            </ul>
        </div>
        <div class="card-body">
            <div class="tab-content">
                {{-- Equipos inscritos --}}
                <div class="tab-pane fade show active" id="teams" role="tabpanel">
                    @if ($registrations->isEmpty())
                        <p class="text-muted mb-0">Todavía no hay equipos inscritos. Los capitanes inscriben a sus equipos desde la app mientras las inscripciones estén abiertas.</p>
                    @else
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead>
                                <tr><th>Ref.</th><th>Equipo</th><th>Capitán</th><th class="text-center">Jugadores</th><th>Monto</th><th>Pago</th><th>Estado</th><th class="text-right">Acción</th></tr>
                                </thead>
                                <tbody>
                                @foreach ($registrations as $registration)
                                    @php
                                        [$label, $color] = $registration->statusBadge();
                                    @endphp
                                    <tr>
                                        <td>{{ $registration->reference() }}</td>
                                        <td><strong>{{ $registration->team?->name ?? '—' }}</strong></td>
                                        <td>
                                            {{ $registration->team?->owner?->name ?? '—' }}
                                            @if ($registration->team?->owner?->phone)
                                                <br><small class="text-muted">{{ $registration->team->owner->phone }}</small>
                                            @endif
                                        </td>
                                        <td class="text-center">{{ $registration->team?->members_count ?? '—' }}</td>
                                        <td>{{ $bs($registration->amount) }}</td>
                                        <td>
                                            {{ $paymentMethods[$registration->payment_method] ?? '—' }}
                                            @if ($registration->paid_at)<br><small class="text-muted">{{ $registration->paid_at->format('d/m/Y H:i') }}</small>@endif
                                        </td>
                                        <td>
                                            <span class="badge badge-{{ $color }}">{{ $label }}</span>
                                            @if ($registration->cancellation_reason)
                                                <br><small class="text-muted">{{ $registration->cancellation_reason }}</small>
                                            @endif
                                        </td>
                                        <td class="text-right text-nowrap">
                                            @can('tournaments.registrations')
                                                @if ($registration->status === 'pending_payment')
                                                    <button type="button" class="btn btn-sm btn-success" data-toggle="modal" data-target="#payment-modal"
                                                            data-action="{{ route('tournaments.registrations.payment', [$tournament, $registration]) }}"
                                                            data-team="{{ $registration->team?->name }}"><i class="fas fa-cash-register"></i> Pago</button>
                                                @endif
                                                @if ($registration->status !== 'cancelled')
                                                    <button type="button" class="btn btn-sm btn-outline-danger" data-toggle="modal" data-target="#cancel-modal"
                                                            data-action="{{ route('tournaments.registrations.cancel', [$tournament, $registration]) }}"
                                                            data-team="{{ $registration->team?->name }}"
                                                            data-paid="{{ $registration->paid_at ? $bs($registration->amount) : '' }}"><i class="fas fa-ban"></i> Anular</button>
                                                @elseif ($registration->paid_at && ! $registration->refunded_at)
                                                    <form method="POST" action="{{ route('tournaments.registrations.refund', [$tournament, $registration]) }}" class="d-inline"
                                                          data-confirm="¿Confirmas que devolviste {{ $bs($registration->amount) }} a {{ $registration->team?->name }}?">
                                                        @csrf
                                                        <button type="submit" class="btn btn-sm btn-warning"><i class="fas fa-undo"></i> Reembolsada</button>
                                                    </form>
                                                @endif
                                            @endcan
                                        </td>
                                    </tr>
                                @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>

                {{-- Fixture --}}
                <div class="tab-pane fade" id="fixture" role="tabpanel">
                    @can('tournaments.games')
                        <div class="mb-3">
                            <button type="button" class="btn btn-primary btn-sm" data-toggle="modal" data-target="#game-modal"
                                    data-action="{{ route('tournaments.games.store', $tournament) }}" data-method="POST"><i class="fas fa-plus"></i> Agregar partido</button>
                            @if ($games->isEmpty())
                                <form method="POST" action="{{ route('tournaments.games.generate', $tournament) }}" class="d-inline"
                                      data-confirm="¿Generar el fixture todos contra todos con los {{ $confirmedTeams->count() }} equipos confirmados?">
                                    @csrf
                                    <button type="submit" class="btn btn-outline-primary btn-sm" @disabled($confirmedTeams->count() < 2)><i class="fas fa-magic"></i> Generar fixture (todos contra todos)</button>
                                </form>
                            @endif
                        </div>
                    @endcan
                    @forelse ($rounds as $round => $roundGames)
                        <h6 class="mt-3 font-weight-bold">{{ $round }}</h6>
                        <div class="table-responsive">
                            <table class="table table-sm mb-2">
                                <tbody>
                                @foreach ($roundGames as $game)
                                    @php
                                        $gameData = [
                                            'round' => $game->round,
                                            'round_order' => $game->round_order,
                                            'home_team_id' => $game->home_team_id,
                                            'away_team_id' => $game->away_team_id,
                                            'court_field_id' => $game->court_field_id,
                                            'scheduled_at' => $game->scheduled_at?->format('Y-m-d\TH:i'),
                                            'home_score' => $game->home_score,
                                            'away_score' => $game->away_score,
                                            'status' => $game->status,
                                        ];
                                    @endphp
                                    <tr>
                                        <td style="width: 160px;" class="text-muted small">{{ $game->scheduled_at?->format('d/m/Y H:i') ?? 'Sin fecha' }}{{ $game->field ? ' · '.$game->field->name : '' }}</td>
                                        <td class="text-right" style="width: 30%;"><strong>{{ $game->homeTeam?->name ?? 'Por definir' }}</strong></td>
                                        <td class="text-center" style="width: 90px;">
                                            @if ($game->status === 'played')
                                                <span class="badge badge-dark" style="font-size: .95rem;">{{ $game->home_score }} – {{ $game->away_score }}</span>
                                            @elseif ($game->status === 'cancelled')
                                                <span class="badge badge-secondary">Suspendido</span>
                                            @else
                                                <span class="text-muted">vs</span>
                                            @endif
                                        </td>
                                        <td style="width: 30%;"><strong>{{ $game->awayTeam?->name ?? 'Por definir' }}</strong></td>
                                        <td class="text-right text-nowrap">
                                            @can('tournaments.games')
                                                <button type="button" class="btn btn-link btn-sm" title="Editar / resultado" data-toggle="modal" data-target="#game-modal"
                                                        data-action="{{ route('tournaments.games.update', [$tournament, $game]) }}" data-method="PUT"
                                                        data-game="{{ json_encode($gameData) }}">
                                                    <i class="fas fa-edit"></i> {{ $game->status === 'played' ? 'Editar' : 'Resultado' }}
                                                </button>
                                                <form method="POST" action="{{ route('tournaments.games.destroy', [$tournament, $game]) }}" class="d-inline" data-confirm="¿Eliminar este partido del fixture?">
                                                    @csrf @method('DELETE')
                                                    <button type="submit" class="btn btn-link btn-sm text-danger" title="Eliminar"><i class="fas fa-trash-alt"></i></button>
                                                </form>
                                            @endcan
                                        </td>
                                    </tr>
                                @endforeach
                                </tbody>
                            </table>
                        </div>
                    @empty
                        <p class="text-muted mb-0">Sin partidos todavía. Genera el fixture cuando estén confirmados los equipos, o agrega los partidos uno por uno (por ejemplo, las llaves de eliminación).</p>
                    @endforelse
                </div>

                {{-- Tabla --}}
                <div class="tab-pane fade" id="standings" role="tabpanel">
                    @if ($standings->isEmpty())
                        <p class="text-muted mb-0">La tabla aparece cuando hay equipos confirmados.</p>
                    @else
                        <div class="table-responsive">
                            <table class="table table-striped table-sm mb-0">
                                <thead><tr><th>#</th><th>Equipo</th><th class="text-center">PJ</th><th class="text-center">G</th><th class="text-center">E</th><th class="text-center">P</th><th class="text-center">GF</th><th class="text-center">GC</th><th class="text-center">DIF</th><th class="text-center">PTS</th></tr></thead>
                                <tbody>
                                @foreach ($standings as $index => $row)
                                    <tr>
                                        <td>{{ $index + 1 }}</td>
                                        <td><strong>{{ $row['team'] }}</strong></td>
                                        <td class="text-center">{{ $row['played'] }}</td>
                                        <td class="text-center">{{ $row['won'] }}</td>
                                        <td class="text-center">{{ $row['drawn'] }}</td>
                                        <td class="text-center">{{ $row['lost'] }}</td>
                                        <td class="text-center">{{ $row['goals_for'] }}</td>
                                        <td class="text-center">{{ $row['goals_against'] }}</td>
                                        <td class="text-center">{{ $row['goal_difference'] > 0 ? '+' : '' }}{{ $row['goal_difference'] }}</td>
                                        <td class="text-center"><strong>{{ $row['points'] }}</strong></td>
                                    </tr>
                                @endforeach
                                </tbody>
                            </table>
                        </div>
                        <small class="text-muted">3 puntos por victoria, 1 por empate. Desempate: diferencia de goles, goles a favor.</small>
                    @endif
                </div>

                {{-- Información --}}
                <div class="tab-pane fade" id="info" role="tabpanel">
                    <dl class="row mb-0">
                        <dt class="col-sm-3">Jugadores por equipo</dt>
                        <dd class="col-sm-9">{{ $tournament->min_players_per_team }}{{ $tournament->max_players_per_team ? ' a '.$tournament->max_players_per_team : ' o más' }}</dd>
                        <dt class="col-sm-3">Premios</dt><dd class="col-sm-9">{!! $tournament->prizes ? nl2br(e($tournament->prizes)) : '<span class="text-muted">—</span>' !!}</dd>
                        <dt class="col-sm-3">Descripción</dt><dd class="col-sm-9">{!! $tournament->description ? nl2br(e($tournament->description)) : '<span class="text-muted">—</span>' !!}</dd>
                        <dt class="col-sm-3">Reglamento</dt><dd class="col-sm-9">{!! $tournament->rules ? nl2br(e($tournament->rules)) : '<span class="text-muted">—</span>' !!}</dd>
                        <dt class="col-sm-3">Creado por</dt><dd class="col-sm-9">{{ $tournament->createdBy?->name ?? '—' }} · {{ $tournament->created_at?->format('d/m/Y') }}</dd>
                    </dl>
                </div>
            </div>
        </div>
    </div>

    {{-- Modals --}}
    <div class="modal fade" id="cancel-modal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <form method="POST" class="modal-content">
                @csrf
                <div class="modal-header"><h5 class="modal-title">Anular inscripción de <span data-field="team"></span></h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Cerrar"><span aria-hidden="true">&times;</span></button></div>
                <div class="modal-body">
                    @include('partials.errors', ['bag' => 'cancel'])
                    <p class="text-danger mb-2 d-none" data-field="paid-warning">La inscripción está pagada: deberás devolver <strong data-field="paid"></strong> al capitán.</p>
                    <label for="cancellation_reason">Motivo *</label>
                    <textarea id="cancellation_reason" name="cancellation_reason" rows="3" required minlength="5" maxlength="500" class="form-control"
                              placeholder="Ej.: el equipo no cumple el reglamento, pidió la baja...">{{ old('cancellation_reason') }}</textarea>
                    <small class="text-muted">El capitán y su equipo verán este motivo en la app.</small>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-default" data-dismiss="modal">Volver</button>
                    <button type="submit" class="btn btn-danger"><i class="fas fa-ban"></i> Anular inscripción</button>
                </div>
            </form>
        </div>
    </div>

    <div class="modal fade" id="payment-modal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-sm">
            <form method="POST" class="modal-content">
                @csrf
                <div class="modal-header"><h5 class="modal-title">Registrar pago</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Cerrar"><span aria-hidden="true">&times;</span></button></div>
                <div class="modal-body">
                    <p>Inscripción de <strong data-field="team"></strong>: {{ $bs($tournament->entry_fee) }}</p>
                    <label for="payment_method">Método *</label>
                    <select id="payment_method" name="payment_method" class="form-control">
                        <option value="cash">Efectivo</option>
                        <option value="transfer">Transferencia</option>
                        <option value="qr">QR</option>
                    </select>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-default" data-dismiss="modal">Volver</button>
                    <button type="submit" class="btn btn-success"><i class="fas fa-check"></i> Registrar</button>
                </div>
            </form>
        </div>
    </div>

    <div class="modal fade" id="game-modal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <form method="POST" class="modal-content" id="game-form">
                @csrf
                <input type="hidden" name="_method" value="POST">
                <div class="modal-header"><h5 class="modal-title">Partido del fixture</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Cerrar"><span aria-hidden="true">&times;</span></button></div>
                <div class="modal-body">
                    @include('partials.errors', ['bag' => 'game'])
                    <div class="row">
                        <div class="col-md-8 form-group">
                            <label for="game-round">Fecha / ronda *</label>
                            <input id="game-round" name="round" required maxlength="40" class="form-control" placeholder="Ej.: Fecha 1, Cuartos de final, Final" value="{{ old('round') }}">
                        </div>
                        <div class="col-md-4 form-group">
                            <label for="game-round-order">Orden</label>
                            <input type="number" min="1" id="game-round-order" name="round_order" class="form-control" value="{{ old('round_order', 1) }}">
                        </div>
                        <div class="col-md-6 form-group">
                            <label for="game-home">Local</label>
                            <select id="game-home" name="home_team_id" class="form-control">
                                <option value="">Por definir</option>
                                @foreach ($confirmedTeams as $team)
                                    <option value="{{ $team->id }}">{{ $team->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6 form-group">
                            <label for="game-away">Visitante</label>
                            <select id="game-away" name="away_team_id" class="form-control">
                                <option value="">Por definir</option>
                                @foreach ($confirmedTeams as $team)
                                    <option value="{{ $team->id }}">{{ $team->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6 form-group">
                            <label for="game-date">Día y hora</label>
                            <input type="datetime-local" id="game-date" name="scheduled_at" class="form-control">
                        </div>
                        <div class="col-md-6 form-group">
                            <label for="game-field">Cancha</label>
                            <select id="game-field" name="court_field_id" class="form-control">
                                <option value="">Sin asignar</option>
                                @foreach ($tournament->court->fields as $field)
                                    <option value="{{ $field->id }}">{{ $field->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-3 form-group">
                            <label for="game-home-score">Resultado local</label>
                            <input type="number" min="0" id="game-home-score" name="home_score" class="form-control">
                        </div>
                        <div class="col-md-3 form-group">
                            <label for="game-away-score">Resultado visitante</label>
                            <input type="number" min="0" id="game-away-score" name="away_score" class="form-control">
                        </div>
                        <div class="col-md-6 form-group">
                            <label for="game-status">Estado</label>
                            <select id="game-status" name="status" class="form-control">
                                @foreach (\App\Models\TournamentGame::STATUSES as $value => $label)
                                    <option value="{{ $value }}">{{ $label }}</option>
                                @endforeach
                            </select>
                            <small class="text-muted">Al cargar el resultado el partido pasa a "Jugado" y suma a la tabla.</small>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-default" data-dismiss="modal">Volver</button>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Guardar</button>
                </div>
            </form>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        (function () {
            // Open the tab in the URL hash (e.g. after saving a game).
            if (location.hash) { $('a[href="' + location.hash + '"]').tab('show'); }

            $('#cancel-modal, #payment-modal').on('show.bs.modal', function (event) {
                const $button = $(event.relatedTarget);
                const $modal = $(this);
                if (!$button.length) return;
                $modal.find('form').attr('action', $button.data('action'));
                $modal.find('[data-field="team"]').text($button.data('team'));
                const paid = $button.data('paid');
                $modal.find('[data-field="paid"]').text(paid || '');
                $modal.find('[data-field="paid-warning"]').toggleClass('d-none', !paid);
            });

            $('#game-modal').on('show.bs.modal', function (event) {
                const $button = $(event.relatedTarget);
                if (!$button.length) return;
                const $form = $('#game-form');
                const game = $button.data('game') || {};
                $form.attr('action', $button.data('action'));
                $form.find('[name="_method"]').val($button.data('method') || 'POST');
                $form.find('[name="round"]').val(game.round || '');
                $form.find('[name="round_order"]').val(game.round_order || 1);
                $form.find('[name="home_team_id"]').val(game.home_team_id || '');
                $form.find('[name="away_team_id"]').val(game.away_team_id || '');
                $form.find('[name="court_field_id"]').val(game.court_field_id || '');
                $form.find('[name="scheduled_at"]').val(game.scheduled_at || '');
                $form.find('[name="home_score"]').val(game.home_score ?? '');
                $form.find('[name="away_score"]').val(game.away_score ?? '');
                $form.find('[name="status"]').val(game.status || 'scheduled');
            });

            @if ($errors->game->any()) $('a[href="#fixture"]').tab('show'); $('#game-modal').modal('show'); @endif
            @if ($errors->cancel->any()) $('#cancel-modal').modal('show'); @endif
        })();
    </script>
@endpush
