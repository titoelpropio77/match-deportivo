<?php

namespace App\Http\Controllers;

use App\Models\Court;
use App\Models\CourtPhoto;
use App\Models\MatchLevel;
use App\Models\Sport;
use App\Models\Team;
use App\Models\Tournament;
use App\Models\TournamentGame;
use App\Models\TournamentRegistration;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Yajra\DataTables\Facades\DataTables;

/**
 * Tournaments of the venues the user can see: partners their own venues, managers the assigned ones.
 */
class TournamentController extends Controller
{
    public function index(Request $request): View
    {
        return view('tournaments.index', [
            'courts' => Court::query()->visibleTo($request->user())->orderBy('name')->get(['id', 'name']),
            'statuses' => Tournament::STATUSES,
            'sports' => Sport::query()->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function data(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'court_id' => ['nullable', 'integer'],
            'status' => ['nullable', Rule::in(array_keys(Tournament::STATUSES))],
            'sport_id' => ['nullable', 'integer'],
        ]);

        $query = Tournament::query()
            ->visibleTo($request->user())
            ->select('tournaments.*')
            ->with(['court:id,name', 'sport:id,name'])
            ->withCount([
                'registrations as confirmed_count' => fn (Builder $query) => $query->where('status', TournamentRegistration::STATUS_CONFIRMED),
            ])
            ->when($filters['court_id'] ?? null, fn (Builder $query, $id) => $query->where('court_id', $id))
            ->when($filters['status'] ?? null, fn (Builder $query, $status) => $query->where('status', $status))
            ->when($filters['sport_id'] ?? null, fn (Builder $query, $id) => $query->where('sport_id', $id));

        return DataTables::eloquent($query)
            ->editColumn('name', fn (Tournament $tournament) => e($tournament->name)
                .'<br><small class="text-muted">'.e(Tournament::FORMATS[$tournament->format] ?? $tournament->format).'</small>')
            ->addColumn('venue', fn (Tournament $tournament) => e($tournament->court->name))
            ->filterColumn('venue', function (Builder $query, string $keyword): void {
                $query->whereHas('court', fn (Builder $court) => $court->where('name', 'ilike', "%{$keyword}%"));
            })
            ->addColumn('sport', fn (Tournament $tournament) => e($tournament->sport->name))
            ->editColumn('starts_on', fn (Tournament $tournament) => $tournament->starts_on->format('d/m/Y'))
            ->orderColumn('starts_on', fn (Builder $query, string $direction) => $query->orderBy('starts_on', $direction)->orderByDesc('tournaments.id'))
            ->addColumn('teams', fn (Tournament $tournament) => $tournament->confirmed_count.' / '.$tournament->max_teams)
            ->editColumn('entry_fee', fn (Tournament $tournament) => (float) $tournament->entry_fee > 0
                ? 'Bs '.number_format((float) $tournament->entry_fee, 2)
                : '<span class="badge badge-light">Gratis</span>')
            ->addColumn('status_badge', fn (Tournament $tournament) => '<span class="badge badge-'.$tournament->statusColor().'">'.e($tournament->statusLabel()).'</span>')
            ->addColumn('action', fn (Tournament $tournament) => view('tournaments.partials.actions', ['tournament' => $tournament])->render())
            ->rawColumns(['name', 'entry_fee', 'status_badge', 'action'])
            ->toJson();
    }

    public function create(Request $request): View
    {
        return view('tournaments.create', [
            'tournament' => new Tournament([
                'format' => 'league',
                'gender' => 'mixed',
                'status' => 'draft',
                'max_teams' => 8,
                'min_players_per_team' => 5,
                'entry_fee' => 0,
            ]),
            ...$this->formOptions($request),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validateTournament($request);
        Gate::authorize('manage', Court::query()->findOrFail($validated['court_id']));

        $tournament = DB::transaction(function () use ($validated, $request): Tournament {
            $tournament = Tournament::query()->create([
                ...collect($validated)->except(['cover', 'remove_cover'])->all(),
                'created_by' => $request->user()->id,
            ]);
            if ($request->hasFile('cover')) {
                $tournament->update(['cover_path' => $request->file('cover')->store("tournaments/{$tournament->id}", CourtPhoto::DISK)]);
            }

            return $tournament;
        });

        return redirect()->route('tournaments.show', $tournament)->with('success', "Torneo {$tournament->name} creado.");
    }

    public function show(Tournament $tournament): View
    {
        $this->authorizeTournament($tournament);

        $tournament->load(['court.fields', 'sport', 'level', 'createdBy']);
        $registrations = $tournament->registrations()
            ->with(['team' => fn ($team) => $team->withCount('members')->with('owner'), 'registeredBy', 'cancelledBy'])
            ->orderByRaw("case status when 'confirmed' then 0 when 'pending_payment' then 1 else 2 end")
            ->orderBy('created_at')
            ->get();
        $games = $tournament->games()->with(['homeTeam', 'awayTeam', 'field'])->get();
        $confirmedTeams = $registrations->where('status', TournamentRegistration::STATUS_CONFIRMED)->pluck('team')->filter()->values();

        return view('tournaments.show', [
            'tournament' => $tournament,
            'registrations' => $registrations,
            'games' => $games,
            'rounds' => $games->groupBy('round'),
            'confirmedTeams' => $confirmedTeams,
            'standings' => $tournament->standings(),
            'collected' => (float) $registrations->whereNotNull('paid_at')->whereNull('refunded_at')->sum('amount'),
            'paymentMethods' => TournamentRegistration::PAYMENT_METHODS,
        ]);
    }

    public function edit(Request $request, Tournament $tournament): View
    {
        $this->authorizeTournament($tournament);

        return view('tournaments.edit', ['tournament' => $tournament, ...$this->formOptions($request)]);
    }

    public function update(Request $request, Tournament $tournament): RedirectResponse
    {
        $this->authorizeTournament($tournament);
        $validated = $this->validateTournament($request, $tournament);
        Gate::authorize('manage', Court::query()->findOrFail($validated['court_id']));

        $previousCover = $tournament->cover_path;
        $attributes = collect($validated)->except(['cover', 'remove_cover'])->all();
        if ($request->hasFile('cover')) {
            $attributes['cover_path'] = $request->file('cover')->store("tournaments/{$tournament->id}", CourtPhoto::DISK);
        } elseif ($request->boolean('remove_cover')) {
            $attributes['cover_path'] = null;
        }
        $tournament->update($attributes);
        if ($previousCover && $previousCover !== $tournament->cover_path) {
            Storage::disk(CourtPhoto::DISK)->delete($previousCover);
        }

        return redirect()->route('tournaments.show', $tournament)->with('success', "Torneo {$tournament->name} actualizado.");
    }

    /**
     * Quick status change from the detail (open/close registrations, start, finish, cancel).
     */
    public function changeStatus(Request $request, Tournament $tournament): RedirectResponse
    {
        $this->authorizeTournament($tournament);
        $validated = $request->validate(['status' => ['required', Rule::in(array_keys(Tournament::STATUSES))]]);

        if ($validated['status'] === 'open' && $tournament->registration_closes_at->isPast()) {
            return back()->with('error', 'El cierre de inscripciones ya pasó: edita la fecha antes de abrirlas.');
        }

        $tournament->update(['status' => $validated['status']]);

        return back()->with('success', 'Estado del torneo: '.$tournament->statusLabel().'.');
    }

    public function destroy(Tournament $tournament): JsonResponse
    {
        $this->authorizeTournament($tournament);

        if ($tournament->registrations()->whereNotNull('paid_at')->whereNull('refunded_at')->exists()) {
            return response()->json([
                'message' => 'No se puede eliminar: hay inscripciones pagadas sin reembolsar. Cancela el torneo y registra las devoluciones.',
            ], 422);
        }

        $cover = $tournament->cover_path;
        $tournament->delete();
        if ($cover) {
            Storage::disk(CourtPhoto::DISK)->delete($cover);
        }

        return response()->json(['message' => "Torneo {$tournament->name} eliminado."]);
    }

    // ── Registrations ─────────────────────────────────────────────────────────

    public function cancelRegistration(Request $request, Tournament $tournament, TournamentRegistration $registration): RedirectResponse
    {
        $this->authorizeTournament($tournament);
        $validated = $request->validateWithBag('cancel', [
            'cancellation_reason' => ['required', 'string', 'min:5', 'max:500'],
        ], [], ['cancellation_reason' => 'motivo']);

        if ($registration->status === TournamentRegistration::STATUS_CANCELLED) {
            return back()->with('error', 'La inscripción ya estaba anulada.');
        }

        $registration->update([
            'status' => TournamentRegistration::STATUS_CANCELLED,
            'cancelled_at' => now(),
            'cancelled_by' => $request->user()->id,
            'cancellation_reason' => $validated['cancellation_reason'],
        ]);

        $message = "Inscripción de {$registration->team?->name} anulada.";
        if ($registration->paid_at) {
            $message .= ' Recuerda devolver Bs '.number_format((float) $registration->amount, 2).'.';
        }

        return back()->with('success', $message);
    }

    /**
     * Entry fee paid at the venue (cash/transfer) for a pending registration.
     */
    public function registerPayment(Request $request, Tournament $tournament, TournamentRegistration $registration): RedirectResponse
    {
        $this->authorizeTournament($tournament);
        $validated = $request->validate(['payment_method' => ['required', Rule::in(['cash', 'transfer', 'qr'])]]);

        if ($registration->status !== TournamentRegistration::STATUS_PENDING_PAYMENT) {
            return back()->with('error', 'Esa inscripción no tiene un pago pendiente.');
        }
        if ($registration->isExpired() && $tournament->spotsTaken() >= $tournament->max_teams) {
            return back()->with('error', 'La inscripción expiró y el torneo ya no tiene cupos.');
        }

        $registration->update([
            'status' => TournamentRegistration::STATUS_CONFIRMED,
            'payment_method' => $validated['payment_method'],
            'paid_at' => now(),
        ]);

        return back()->with('success', "Pago de {$registration->team?->name} registrado.");
    }

    public function refundRegistration(Tournament $tournament, TournamentRegistration $registration): RedirectResponse
    {
        $this->authorizeTournament($tournament);

        if ($registration->status !== TournamentRegistration::STATUS_CANCELLED || ! $registration->paid_at || $registration->refunded_at) {
            return back()->with('error', 'Esa inscripción no tiene una devolución pendiente.');
        }

        $registration->update(['refunded_at' => now()]);

        return back()->with('success', 'Devolución registrada.');
    }

    // ── Fixture ───────────────────────────────────────────────────────────────

    public function storeGame(Request $request, Tournament $tournament): RedirectResponse
    {
        $this->authorizeTournament($tournament);
        $tournament->games()->create($this->validateGame($request, $tournament));

        return redirect()->to(route('tournaments.show', $tournament).'#fixture')->with('success', 'Partido agregado al fixture.');
    }

    /**
     * Edit a game: teams, date, court and/or result.
     */
    public function updateGame(Request $request, Tournament $tournament, TournamentGame $game): RedirectResponse
    {
        $this->authorizeTournament($tournament);
        abort_unless($game->tournament_id === $tournament->id, 404);

        $validated = $this->validateGame($request, $tournament);
        // A result marks the game as played.
        if (isset($validated['home_score'], $validated['away_score']) && ($validated['status'] ?? null) === TournamentGame::STATUS_SCHEDULED) {
            $validated['status'] = TournamentGame::STATUS_PLAYED;
        }
        $game->update($validated);

        return redirect()->to(route('tournaments.show', $tournament).'#fixture')->with('success', 'Partido actualizado.');
    }

    public function destroyGame(Tournament $tournament, TournamentGame $game): RedirectResponse
    {
        $this->authorizeTournament($tournament);
        abort_unless($game->tournament_id === $tournament->id, 404);
        $game->delete();

        return redirect()->to(route('tournaments.show', $tournament).'#fixture')->with('success', 'Partido eliminado.');
    }

    /**
     * Round robin ("todos contra todos") between the confirmed teams, one round per week from the start date.
     */
    public function generateFixture(Tournament $tournament): RedirectResponse
    {
        $this->authorizeTournament($tournament);

        if ($tournament->games()->exists()) {
            return back()->with('error', 'El torneo ya tiene partidos. Elimínalos para generar el fixture de nuevo.');
        }
        $teams = $tournament->registrations()->where('status', TournamentRegistration::STATUS_CONFIRMED)->pluck('team_id')->shuffle()->values()->all();
        if (count($teams) < 2) {
            return back()->with('error', 'Se necesitan al menos 2 equipos confirmados para generar el fixture.');
        }

        // Circle method: with an odd number of teams one rests each round (null).
        if (count($teams) % 2 === 1) {
            $teams[] = null;
        }
        $count = count($teams);
        $rounds = $count - 1;

        DB::transaction(function () use ($tournament, $teams, $count, $rounds): void {
            for ($round = 0; $round < $rounds; $round++) {
                for ($i = 0; $i < $count / 2; $i++) {
                    [$home, $away] = [$teams[$i], $teams[$count - 1 - $i]];
                    if ($home === null || $away === null) {
                        continue;
                    }
                    // Alternate home/away so nobody always plays first.
                    if ($round % 2 === 1) {
                        [$home, $away] = [$away, $home];
                    }
                    $tournament->games()->create([
                        'round' => 'Fecha '.($round + 1),
                        'round_order' => $round + 1,
                        'home_team_id' => $home,
                        'away_team_id' => $away,
                        'scheduled_at' => $tournament->starts_on->copy()->addWeeks($round)->setTime(19, 0),
                        'status' => TournamentGame::STATUS_SCHEDULED,
                    ]);
                }
                // Rotate every team but the first.
                $teams = [$teams[0], $teams[$count - 1], ...array_slice($teams, 1, $count - 2)];
            }
        });

        return redirect()->to(route('tournaments.show', $tournament).'#fixture')
            ->with('success', "Fixture generado: {$rounds} fechas. Ajusta días, horas y canchas si hace falta.");
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function authorizeTournament(Tournament $tournament): void
    {
        Gate::authorize('manage', $tournament->court()->firstOrFail());
    }

    /**
     * @return array<string, mixed>
     */
    private function formOptions(Request $request): array
    {
        return [
            'courts' => Court::query()->visibleTo($request->user())->orderBy('name')->get(['id', 'name']),
            'sports' => Sport::query()->orderBy('name')->get(['id', 'name']),
            'levels' => MatchLevel::query()->orderBy('order')->get(['id', 'name']),
            'formats' => Tournament::FORMATS,
            'genders' => Tournament::GENDERS,
            'statuses' => Tournament::STATUSES,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function validateTournament(Request $request, ?Tournament $tournament = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'court_id' => ['required', 'integer', 'exists:courts,id'],
            'sport_id' => ['required', 'integer', 'exists:sports,id'],
            'level_id' => ['nullable', 'integer', 'exists:match_levels,id'],
            'format' => ['required', Rule::in(array_keys(Tournament::FORMATS))],
            'gender' => ['required', Rule::in(array_keys(Tournament::GENDERS))],
            'entry_fee' => ['required', 'numeric', 'min:0', 'max:100000'],
            'prizes' => ['nullable', 'string', 'max:2000'],
            'max_teams' => ['required', 'integer', 'min:2', 'max:256'],
            'min_players_per_team' => ['required', 'integer', 'min:1', 'max:50'],
            'max_players_per_team' => ['nullable', 'integer', 'gte:min_players_per_team', 'max:60'],
            'registration_closes_at' => ['required', 'date', $tournament ? 'nullable' : 'after:now'],
            'starts_on' => array_filter(['required', 'date', $request->filled('registration_closes_at')
                ? 'after_or_equal:'.substr((string) $request->input('registration_closes_at'), 0, 10)
                : null]),
            'ends_on' => ['nullable', 'date', 'after_or_equal:starts_on'],
            'status' => ['required', Rule::in(array_keys(Tournament::STATUSES))],
            'description' => ['nullable', 'string', 'max:5000'],
            'rules' => ['nullable', 'string', 'max:10000'],
            'cover' => ['nullable', 'image', 'mimes:jpeg,jpg,png,webp', 'max:5120'],
            'remove_cover' => ['sometimes', 'boolean'],
        ], [
            'starts_on.after_or_equal' => 'El torneo debe empezar después del cierre de inscripciones.',
            'max_players_per_team.gte' => 'El máximo de jugadores no puede ser menor al mínimo.',
            'registration_closes_at.after' => 'El cierre de inscripciones debe ser una fecha futura.',
        ], [
            'name' => 'nombre',
            'court_id' => 'centro deportivo',
            'sport_id' => 'deporte',
            'level_id' => 'nivel',
            'format' => 'formato',
            'gender' => 'categoría',
            'entry_fee' => 'costo de inscripción',
            'prizes' => 'premios',
            'max_teams' => 'cupo de equipos',
            'min_players_per_team' => 'mínimo de jugadores',
            'max_players_per_team' => 'máximo de jugadores',
            'registration_closes_at' => 'cierre de inscripciones',
            'starts_on' => 'fecha de inicio',
            'ends_on' => 'fecha de fin',
            'status' => 'estado',
            'description' => 'descripción',
            'rules' => 'reglamento',
            'cover' => 'portada',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function validateGame(Request $request, Tournament $tournament): array
    {
        $teamIds = $tournament->registrations()->pluck('team_id')->all();

        return $request->validateWithBag('game', [
            'round' => ['required', 'string', 'max:40'],
            'round_order' => ['nullable', 'integer', 'min:1', 'max:999'],
            'home_team_id' => ['nullable', 'integer', Rule::in($teamIds)],
            'away_team_id' => ['nullable', 'integer', Rule::in($teamIds), 'different:home_team_id'],
            'court_field_id' => ['nullable', 'integer', Rule::exists('court_fields', 'id')->where('court_id', $tournament->court_id)],
            'scheduled_at' => ['nullable', 'date'],
            'home_score' => ['nullable', 'integer', 'min:0', 'max:999', 'required_with:away_score'],
            'away_score' => ['nullable', 'integer', 'min:0', 'max:999', 'required_with:home_score'],
            'status' => ['nullable', Rule::in(array_keys(TournamentGame::STATUSES))],
        ], [
            'away_team_id.different' => 'Un equipo no puede jugar contra sí mismo.',
        ], [
            'round' => 'fecha / ronda',
            'home_team_id' => 'equipo local',
            'away_team_id' => 'equipo visitante',
            'court_field_id' => 'cancha',
            'scheduled_at' => 'día y hora',
            'home_score' => 'goles/puntos del local',
            'away_score' => 'goles/puntos del visitante',
        ]) + ['round_order' => $request->integer('round_order') ?: 1, 'status' => $request->input('status') ?: TournamentGame::STATUS_SCHEDULED];
    }
}
