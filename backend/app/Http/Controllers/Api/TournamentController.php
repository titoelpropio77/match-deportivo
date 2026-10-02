<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\TournamentResource;
use App\Models\Team;
use App\Models\Tournament;
use App\Models\TournamentGame;
use App\Models\TournamentRegistration;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Tournaments in the app: browse, see teams/fixture/table, sign up a team and pay its entry fee.
 * Tournaments themselves are created from the admin panel.
 */
class TournamentController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'sport_id' => ['nullable', 'integer', 'exists:sports,id'],
            'city_id' => ['nullable', 'integer', 'exists:cities,id'],
            // open: taking registrations; upcoming: not finished yet (default); finished.
            'scope' => ['nullable', Rule::in(['open', 'upcoming', 'finished'])],
        ]);
        $scope = $validated['scope'] ?? 'upcoming';

        $tournaments = Tournament::query()
            ->published()
            ->with(['sport', 'level', 'court.city'])
            ->withCount(['registrations' => fn (Builder $query) => $query->where('status', TournamentRegistration::STATUS_CONFIRMED)])
            ->when($validated['sport_id'] ?? null, fn (Builder $query, $sportId) => $query->where('sport_id', $sportId))
            ->when($validated['city_id'] ?? null, fn (Builder $query, $cityId) => $query->whereHas('court', fn (Builder $court) => $court->where('city_id', $cityId)))
            ->when($scope === 'open', fn (Builder $query) => $query
                ->where('status', Tournament::STATUS_OPEN)
                ->where('registration_closes_at', '>', now()))
            ->when($scope === 'upcoming', fn (Builder $query) => $query->where('status', '!=', Tournament::STATUS_FINISHED))
            ->when($scope === 'finished', fn (Builder $query) => $query->where('status', Tournament::STATUS_FINISHED))
            ->orderBy($scope === 'finished' ? 'ends_on' : 'starts_on', $scope === 'finished' ? 'desc' : 'asc')
            ->limit(50)
            ->get();

        return response()->json(['data' => TournamentResource::collection($tournaments)]);
    }

    /**
     * Detail with confirmed teams, fixture, standings and the current user's registration.
     */
    public function show(Request $request, Tournament $tournament): JsonResponse
    {
        abort_unless(in_array($tournament->status, Tournament::PUBLIC_STATUSES, true), 404);

        $tournament->load(['sport', 'level', 'court.city'])
            ->loadCount(['registrations' => fn (Builder $query) => $query->where('status', TournamentRegistration::STATUS_CONFIRMED)]);

        $confirmed = $tournament->registrations()
            ->with(['team' => fn ($team) => $team->withCount('members')])
            ->where('status', TournamentRegistration::STATUS_CONFIRMED)
            ->orderBy('paid_at')
            ->orderBy('id')
            ->get();

        $games = $tournament->games()->with(['homeTeam', 'awayTeam', 'field:id,name'])->get();

        return response()->json([
            'data' => [
                ...(new TournamentResource($tournament))->resolve($request),
                'description' => $tournament->description,
                'rules' => $tournament->rules,
                'teams' => $confirmed->map(fn (TournamentRegistration $registration) => $this->team($registration->team))->values(),
                'games' => $games->map(fn (TournamentGame $game) => [
                    'id' => $game->id,
                    'round' => $game->round,
                    'round_order' => $game->round_order,
                    'scheduled_at' => $game->scheduled_at?->toIso8601String(),
                    'field' => $game->field?->name,
                    'status' => $game->status,
                    'home_team' => $game->homeTeam ? $this->team($game->homeTeam) : null,
                    'away_team' => $game->awayTeam ? $this->team($game->awayTeam) : null,
                    'home_score' => $game->home_score,
                    'away_score' => $game->away_score,
                ])->values(),
                'standings' => $tournament->standings(),
                'my_registrations' => $this->registrationsOf($request, $tournament)->values(),
            ],
        ]);
    }

    /**
     * Tournaments where a team of the user is registered (or awaiting payment).
     */
    public function mine(Request $request): JsonResponse
    {
        $userId = $request->user()->id;
        $registrations = TournamentRegistration::query()
            ->with(['team', 'tournament' => fn ($query) => $query->with(['sport', 'level', 'court.city'])])
            ->whereHas('team.members', fn (Builder $members) => $members->where('user_id', $userId))
            ->where(fn (Builder $query) => $query
                ->active()
                ->orWhere(fn (Builder $cancelled) => $cancelled
                    ->where('status', TournamentRegistration::STATUS_CANCELLED)
                    ->whereColumn('cancelled_by', '!=', 'registered_by')))
            ->latest()
            ->get()
            ->filter(fn (TournamentRegistration $registration) => in_array($registration->tournament->status, Tournament::PUBLIC_STATUSES, true)
                || $registration->tournament->status === Tournament::STATUS_CANCELLED);

        return response()->json([
            'data' => $registrations->map(fn (TournamentRegistration $registration) => [
                'tournament' => (new TournamentResource($registration->tournament))->resolve($request),
                'registration' => $this->registration($registration),
            ])->values(),
        ]);
    }

    /**
     * Sign up a team (only its captain). Free tournaments confirm right away; otherwise the spot is
     * held while the entry fee is paid with the (simulated) QR.
     */
    public function register(Request $request, Tournament $tournament): JsonResponse
    {
        $validated = $request->validate(['team_id' => ['required', 'integer', 'exists:teams,id']]);
        $user = $request->user();

        $registration = DB::transaction(function () use ($tournament, $validated, $user): TournamentRegistration {
            $tournament = Tournament::query()->lockForUpdate()->findOrFail($tournament->id);
            $team = Team::query()->withCount('members')->findOrFail($validated['team_id']);
            $fail = fn (string $message) => throw ValidationException::withMessages(['team_id' => $message]);

            if ($team->owner_id !== $user->id) {
                $fail('Solo el capitán del equipo puede inscribirlo.');
            }
            if ($tournament->status !== Tournament::STATUS_OPEN || $tournament->registration_closes_at->isPast()) {
                $fail('Las inscripciones de este torneo están cerradas.');
            }
            if ($team->sport_id !== $tournament->sport_id) {
                $fail("{$team->name} no es un equipo del deporte del torneo.");
            }
            if ($tournament->gender->value !== 'mixed' && $team->gender?->value !== $tournament->gender->value) {
                $fail('La categoría del equipo no coincide con la del torneo.');
            }
            if ($team->members_count < $tournament->min_players_per_team) {
                $fail("El torneo pide al menos {$tournament->min_players_per_team} jugadores; {$team->name} tiene {$team->members_count}.");
            }
            if ($tournament->max_players_per_team && $team->members_count > $tournament->max_players_per_team) {
                $fail("El torneo permite como máximo {$tournament->max_players_per_team} jugadores por equipo; {$team->name} tiene {$team->members_count}.");
            }

            $existing = $tournament->registrations()->where('team_id', $team->id)->lockForUpdate()->first();
            if ($existing && ($existing->status === TournamentRegistration::STATUS_CONFIRMED || ($existing->status === TournamentRegistration::STATUS_PENDING_PAYMENT && ! $existing->isExpired()))) {
                $fail("{$team->name} ya está inscrito en este torneo.");
            }
            if ($tournament->spotsLeft() <= 0) {
                $fail('El torneo ya no tiene cupos.');
            }

            $free = (float) $tournament->entry_fee <= 0;
            $attributes = [
                'registered_by' => $user->id,
                'status' => $free ? TournamentRegistration::STATUS_CONFIRMED : TournamentRegistration::STATUS_PENDING_PAYMENT,
                'amount' => $tournament->entry_fee,
                'payment_method' => $free ? 'free' : null,
                'paid_at' => null,
                'cancelled_at' => null,
                'cancelled_by' => null,
                'cancellation_reason' => null,
                'refunded_at' => null,
            ];

            if ($existing) {
                // A cancelled or expired registration of the same team starts over.
                $existing->forceFill([...$attributes, 'created_at' => now()])->save();

                return $existing;
            }

            return $tournament->registrations()->create([...$attributes, 'team_id' => $team->id]);
        });

        return response()->json(['data' => $this->registration($registration->fresh('team'))], 201);
    }

    /**
     * Simulated QR payment of the entry fee.
     * TODO: replace with the bank QR webhook once the payment provider is connected.
     */
    public function pay(Request $request, TournamentRegistration $registration): JsonResponse
    {
        $this->authorizeCaptain($request, $registration);

        if ($registration->status !== TournamentRegistration::STATUS_CONFIRMED) {
            if ($registration->status !== TournamentRegistration::STATUS_PENDING_PAYMENT || $registration->isExpired()) {
                throw ValidationException::withMessages([
                    'registration' => 'La inscripción expiró o fue anulada. Vuelve a inscribir al equipo.',
                ]);
            }
            $registration->update([
                'status' => TournamentRegistration::STATUS_CONFIRMED,
                'payment_method' => 'qr',
                'paid_at' => now(),
            ]);
        }

        return response()->json(['data' => $this->registration($registration->fresh('team'))]);
    }

    /**
     * The captain gives up an unpaid registration (frees the spot).
     */
    public function cancel(Request $request, TournamentRegistration $registration): JsonResponse
    {
        $this->authorizeCaptain($request, $registration);

        if ($registration->status !== TournamentRegistration::STATUS_PENDING_PAYMENT) {
            throw ValidationException::withMessages([
                'registration' => 'Solo puedes cancelar una inscripción pendiente de pago. Para darte de baja habla con el centro deportivo.',
            ]);
        }

        $registration->update([
            'status' => TournamentRegistration::STATUS_CANCELLED,
            'cancelled_at' => now(),
            'cancelled_by' => $request->user()->id,
        ]);

        return response()->json(null, 204);
    }

    private function authorizeCaptain(Request $request, TournamentRegistration $registration): void
    {
        $registration->loadMissing('team');
        abort_unless($registration->team?->owner_id === $request->user()->id, 403, 'Solo el capitán del equipo puede hacerlo.');
    }

    /**
     * Registrations of teams the user plays in, for this tournament.
     */
    private function registrationsOf(Request $request, Tournament $tournament)
    {
        return $tournament->registrations()
            ->with('team')
            ->whereHas('team.members', fn (Builder $members) => $members->where('user_id', $request->user()->id))
            ->where('status', '!=', TournamentRegistration::STATUS_CANCELLED)
            ->get()
            ->reject(fn (TournamentRegistration $registration) => $registration->isExpired())
            ->map(fn (TournamentRegistration $registration) => $this->registration($registration));
    }

    /**
     * @return array<string, mixed>
     */
    private function registration(TournamentRegistration $registration): array
    {
        return [
            'id' => $registration->id,
            'status' => $registration->displayStatus(),
            'amount' => (float) $registration->amount,
            'payment_reference' => $registration->reference(),
            'payment_expires_at' => $registration->paymentExpiresAt(),
            'paid_at' => $registration->paid_at?->toIso8601String(),
            'cancellation_reason' => $registration->cancellation_reason,
            'refunded' => $registration->refunded_at !== null,
            'tournament_id' => $registration->tournament_id,
            'team' => $registration->team ? $this->team($registration->team) : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function team(Team $team): array
    {
        return [
            'id' => $team->id,
            'name' => $team->name,
            'short_name' => $team->short_name,
            'primary_color' => $team->primary_color,
            'logo_url' => $team->logo_url,
            'owner_id' => $team->owner_id,
            'members_count' => $team->members_count ?? null,
        ];
    }
}
