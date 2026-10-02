<?php

namespace App\Http\Controllers;

use App\DataTables\TournamentDataTable;
use App\Http\Requests\Reservations\CancelReservationRequest;
use App\Http\Requests\Tournaments\RegistrationPaymentRequest;
use App\Http\Requests\Tournaments\TournamentGameRequest;
use App\Http\Requests\Tournaments\TournamentRequest;
use App\Http\Requests\Tournaments\TournamentStatusRequest;
use App\Models\Court;
use App\Models\CourtPhoto;
use App\Models\MatchLevel;
use App\Models\Sport;
use App\Models\Team;
use App\Models\Tournament;
use App\Models\TournamentGame;
use App\Models\TournamentRegistration;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

/**
 * Tournaments of the venues the user can see: partners their own venues, managers the assigned ones.
 */
class TournamentController extends Controller
{
    public function index(Request $request, TournamentDataTable $dataTable): mixed
    {
        return $dataTable->render('tournaments.index', [
            'courts' => Court::query()->visibleTo($request->user())->orderBy('name')->get(['id', 'name']),
            'statuses' => Tournament::STATUSES,
            'sports' => Sport::query()->orderBy('name')->get(['id', 'name']),
        ]);
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

    public function store(TournamentRequest $request): RedirectResponse
    {
        $validated = $request->validated();
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

    public function update(TournamentRequest $request, Tournament $tournament): RedirectResponse
    {
        $this->authorizeTournament($tournament);
        $validated = $request->validated();
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
    public function changeStatus(TournamentStatusRequest $request, Tournament $tournament): RedirectResponse
    {
        $this->authorizeTournament($tournament);
        $validated = $request->validated();

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

    public function cancelRegistration(CancelReservationRequest $request, Tournament $tournament, TournamentRegistration $registration): RedirectResponse
    {
        $this->authorizeTournament($tournament);
        $validated = $request->validated();

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
    public function registerPayment(RegistrationPaymentRequest $request, Tournament $tournament, TournamentRegistration $registration): RedirectResponse
    {
        $this->authorizeTournament($tournament);
        $validated = $request->validated();

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

    public function storeGame(TournamentGameRequest $request, Tournament $tournament): RedirectResponse
    {
        $this->authorizeTournament($tournament);
        $tournament->games()->create($request->gameData());

        return redirect()->to(route('tournaments.show', $tournament).'#fixture')->with('success', 'Partido agregado al fixture.');
    }

    /**
     * Edit a game: teams, date, court and/or result.
     */
    public function updateGame(TournamentGameRequest $request, Tournament $tournament, TournamentGame $game): RedirectResponse
    {
        $this->authorizeTournament($tournament);
        abort_unless($game->tournament_id === $tournament->id, 404);

        $validated = $request->gameData();
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
}
