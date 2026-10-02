<?php

namespace App\Http\Controllers;

use App\Enums\MatchGender;
use App\Enums\MatchPlayerStatus;
use App\Enums\MatchStatus;
use App\Enums\RatingPolarity;
use App\Models\CourtField;
use App\Models\MatchModel;
use App\Models\MatchPlayer;
use App\Models\PlayerRating;
use App\Models\RatingTag;
use App\Models\Team;
use App\Models\TrustedPlayer;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class MatchController extends Controller
{
    /**
     * Create a new match for the authenticated user, optionally inviting players.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'sport_id' => ['required', 'integer', 'exists:sports,id'],
            'level_id' => ['required', 'integer', 'exists:match_levels,id'],
            'court_id' => ['required', 'integer', 'exists:courts,id'],
            // Courts of the chosen venue; required whenever the venue has any.
            'court_field_ids' => [
                Rule::requiredIf(fn () => CourtField::query()->where('court_id', $request->integer('court_id'))->exists()),
                'array',
            ],
            'court_field_ids.*' => [
                'integer',
                'distinct',
                Rule::exists('court_fields', 'id')->where('court_id', $request->integer('court_id')),
            ],
            'gender' => ['required', Rule::enum(MatchGender::class)],
            'start_time' => ['required', 'date'],
            'end_time' => ['required', 'date', 'after:start_time'],
            'max_players' => ['required', 'integer', 'min:1', 'max:65535'],
            'player_ids' => ['sometimes', 'array'],
            'player_ids.*' => ['integer', 'distinct', 'exists:users,id'],
            // Every member of each team is added as a confirmed player.
            'team_ids' => ['sometimes', 'array', 'max:10'],
            'team_ids.*' => ['integer', 'distinct', 'exists:teams,id'],
            'join_as_player' => ['sometimes', 'boolean'],
            // Court booking of the organizer this match is played on (from "Mis reservas").
            'booking_code' => [
                'nullable',
                'string',
                Rule::exists('court_reservations', 'booking_code')->where('user_id', $request->user()->getAuthIdentifier()),
            ],
            'payment_qr' => ['nullable', 'image', 'mimes:jpeg,jpg,png,webp', 'max:4096'],
        ], [
            'court_field_ids.required' => 'Selecciona al menos una cancha del centro deportivo.',
            'court_field_ids.*.exists' => 'Una de las canchas no pertenece al centro deportivo seleccionado.',
            'booking_code.exists' => 'Esa reserva no existe o no es tuya.',
        ]);

        $organizerId = $request->user()->getAuthIdentifier();
        $joinAsPlayer = $request->boolean('join_as_player');

        if (! empty($validated['booking_code']) && MatchModel::query()
            ->where('booking_code', $validated['booking_code'])
            ->where('status', '!=', MatchStatus::Cancelled->value)
            ->exists()) {
            throw ValidationException::withMessages([
                'booking_code' => 'Ya creaste un partido con esta reserva.',
            ]);
        }

        $teams = Team::query()
            ->with(['sport', 'members'])
            ->whereIn('id', $validated['team_ids'] ?? [])
            ->get();
        $otherSport = $teams->first(fn (Team $team) => $team->sport_id !== (int) $validated['sport_id']);
        if ($otherSport !== null) {
            throw ValidationException::withMessages([
                'team_ids' => "El equipo {$otherSport->name} es de {$otherSport->sport->name}; elige equipos del deporte del partido.",
            ]);
        }
        $teamMemberIds = $teams->flatMap(fn (Team $team) => $team->members->pluck('user_id'));
        // Adding a team you play in adds you as a player too.
        $joinAsPlayer = $joinAsPlayer || $teamMemberIds->contains($organizerId);

        $playerIds = collect($validated['player_ids'] ?? [])
            ->merge($teamMemberIds)
            ->reject(fn ($id) => $id === $organizerId)
            ->unique()
            ->values();

        $takenSlots = $playerIds->count() + ($joinAsPlayer ? 1 : 0);
        if ($takenSlots > $validated['max_players']) {
            return response()->json([
                'message' => "Los jugadores seleccionados ({$takenSlots}) superan el límite de jugadores del partido ({$validated['max_players']}).",
                'errors' => [
                    'max_players' => ["Los jugadores seleccionados ({$takenSlots}) superan el límite de jugadores del partido ({$validated['max_players']})."],
                ],
            ], 422);
        }

        $paymentQrPath = $request->hasFile('payment_qr')
            ? $request->file('payment_qr')->store('payment-qrs', 'public')
            : null;

        $match = DB::transaction(function () use ($validated, $organizerId, $playerIds, $takenSlots, $joinAsPlayer, $paymentQrPath, $teams): MatchModel {
            $match = MatchModel::create([
                'organizer_id' => $organizerId,
                'sport_id' => $validated['sport_id'],
                'level_id' => $validated['level_id'],
                'court_id' => $validated['court_id'],
                'booking_code' => $validated['booking_code'] ?? null,
                'gender' => $validated['gender'],
                'payment_qr_path' => $paymentQrPath,
                'scheduled_at' => $validated['start_time'],
                'start_time' => $validated['start_time'],
                'end_time' => $validated['end_time'],
                'total_players' => $validated['max_players'],
                'missing_players' => $validated['max_players'] - $takenSlots,
                'max_players' => $validated['max_players'],
                'status' => MatchStatus::Open,
            ]);

            $match->courtFields()->sync($validated['court_field_ids'] ?? []);
            $match->teams()->sync($teams->modelKeys());

            if ($joinAsPlayer) {
                $match->players()->create([
                    'user_id' => $organizerId,
                    'quantity_slots' => 1,
                    'status' => MatchPlayerStatus::Confirmed,
                ]);
            }

            foreach ($playerIds as $playerId) {
                $match->players()->create([
                    'user_id' => $playerId,
                    'quantity_slots' => 1,
                    'status' => MatchPlayerStatus::Confirmed,
                ]);
            }

            return $match->fresh(['players.user', 'organizer', 'sport', 'level', 'court', 'courtFields.sports', 'teams']);
        });

        return response()->json(['data' => $match], 201);
    }

    /**
     * List open matches, optionally filtered by sport, court, city and date.
     */
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'sport_id' => ['sometimes', 'integer', 'exists:sports,id'],
            'court_id' => ['sometimes', 'integer', 'exists:courts,id'],
            'city_id' => ['sometimes', 'integer', 'exists:cities,id'],
            'date' => ['sometimes', 'date'],
        ]);

        $matches = MatchModel::query()
            ->with(['sport', 'level', 'court', 'courtFields.sports'])
            ->whereIn('status', [MatchStatus::Open->value, MatchStatus::Full->value])
            ->when(isset($validated['sport_id']), function (Builder $query) use ($validated): void {
                $query->where('sport_id', $validated['sport_id']);
            })
            ->when(isset($validated['court_id']), function (Builder $query) use ($validated): void {
                $query->where('court_id', $validated['court_id']);
            })
            ->when(isset($validated['city_id']), function (Builder $query) use ($validated): void {
                $query->whereHas('court', fn (Builder $court) => $court->where('city_id', $validated['city_id']));
            })
            ->when(isset($validated['date']), function (Builder $query) use ($validated): void {
                $query->whereDate('start_time', $validated['date']);
            })
            ->where('start_time', '>=', now())
            ->orderBy('start_time')
            ->paginate(15);

        return response()->json($matches);
    }

    /**
     * List the authenticated user's upcoming matches (joined as a player).
     */
    public function mine(Request $request): JsonResponse
    {
        $userId = $request->user()->getAuthIdentifier();

        $matches = MatchModel::query()
            ->with(['sport', 'level', 'court', 'courtFields.sports', 'players.user'])
            ->whereHas('players', function (Builder $playersQuery) use ($userId): void {
                $playersQuery->where('user_id', $userId);
            })
            ->where('status', '!=', MatchStatus::Cancelled->value)
            ->where('start_time', '>=', now())
            ->orderBy('start_time')
            ->paginate(15);

        return response()->json($matches);
    }

    /**
     * List available matches created by the authenticated user (open and still on schedule).
     */
    public function organized(Request $request): JsonResponse
    {
        $userId = $request->user()->getAuthIdentifier();

        $matches = MatchModel::query()
            ->with(['sport', 'level', 'court', 'courtFields.sports'])
            ->where('organizer_id', $userId)
            // ->where('status', MatchStatus::Open->value)
            ->where('end_time', '>=', now())
            ->orderBy('start_time')
            ->paginate(15);

        return response()->json($matches);
    }

    /**
     * List past matches created by the authenticated user, five per page.
     */
    public function organizedPast(Request $request): JsonResponse
    {
        $userId = $request->user()->getAuthIdentifier();

        $matches = MatchModel::query()
            ->with(['sport', 'level', 'court', 'courtFields.sports'])
            ->where('organizer_id', $userId)
            ->where('end_time', '<', now())
            ->orderByDesc('end_time')
            ->paginate(5);

        return response()->json($matches);
    }

    /**
     * Show the full detail of a match, including court, sport, level and players.
     */
    public function show(int $id): JsonResponse
    {
        $match = MatchModel::query()
            ->with([
                'organizer',
                'sport',
                'level',
                'court.photos',
                'court.sports',
                'courtFields.sports',
                'players.user',
                'teams',
            ])
            ->findOrFail($id);

        return response()->json(['data' => $match]);
    }

    /**
     * Join the authenticated user to a match while reserving the requested slots.
     */
    public function join(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'quantity_slots' => ['sometimes', 'integer', 'min:1', 'max:65535'],
        ]);
        $quantitySlots = $validated['quantity_slots'] ?? 1;

        $match = DB::transaction(function () use ($request, $id, $quantitySlots): MatchModel {
            $match = MatchModel::query()->lockForUpdate()->findOrFail($id);
            $userId = $request->user()->getAuthIdentifier();

            if ($match->status === MatchStatus::Cancelled) {
                abort(409, 'This match is not open for registrations.');
            }

            if ($match->players()->where('user_id', $userId)->exists()) {
                abort(409, 'The user is already registered for this match.');
            }

            $hasActiveSlot = $match->status === MatchStatus::Open
                && $match->missing_players >= $quantitySlots;

            if (! $hasActiveSlot) {
                $match->players()->create([
                    'user_id' => $userId,
                    'quantity_slots' => $quantitySlots,
                    'status' => MatchPlayerStatus::Reserved,
                ]);

                return $match->fresh(['players.user', 'organizer', 'sport', 'level', 'court', 'courtFields.sports']);
            }

            $isTrusted = $userId === $match->organizer_id
                || TrustedPlayer::query()
                    ->where('organizer_id', $match->organizer_id)
                    ->where('player_id', $userId)
                    ->exists();

            $match->players()->create([
                'user_id' => $userId,
                'quantity_slots' => $quantitySlots,
                'status' => $isTrusted
                    ? MatchPlayerStatus::Confirmed
                    : MatchPlayerStatus::Pending,
            ]);

            $match->missing_players -= $quantitySlots;
            $match->status = $match->missing_players === 0
                ? MatchStatus::Full
                : MatchStatus::Open;
            $match->save();

            return $match->fresh(['players.user', 'organizer', 'sport', 'level', 'court', 'courtFields.sports']);
        });

        return response()->json(['data' => $match]);
    }

    /**
     * Remove the authenticated user's registration from a match.
     */
    public function leave(Request $request, int $id): JsonResponse
    {
        $userId = $request->user()->getAuthIdentifier();

        $match = DB::transaction(function () use ($userId, $id): MatchModel {
            $match = MatchModel::query()->lockForUpdate()->findOrFail($id);

            if ($match->end_time->lte(now()) || $match->status === MatchStatus::Finished) {
                abort(409, 'No se puede salir de un partido que ya concluyó.');
            }

            $player = $match->players()->where('user_id', $userId)->first();
            if ($player === null) {
                abort(409, 'The user is not registered for this match.');
            }

            $this->releasePlayer($match, $player);

            return $match->fresh(['players.user', 'organizer', 'sport', 'level', 'court', 'courtFields.sports']);
        });

        return response()->json(['data' => $match]);
    }

    /**
     * Soft-delete a match. Only the organizer can delete it, and only before it starts.
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        $userId = $request->user()->getAuthIdentifier();

        DB::transaction(function () use ($userId, $id): void {
            $match = MatchModel::query()->lockForUpdate()->findOrFail($id);

            if ($match->organizer_id !== $userId) {
                abort(403, 'Solo el organizador puede eliminar este partido.');
            }

            if ($match->end_time->lte(now()) || $match->status === MatchStatus::Finished) {
                abort(409, 'No se puede eliminar un partido que ya concluyó.');
            }

            if ($match->start_time->lte(now())) {
                abort(409, 'No se puede eliminar un partido que ya inició.');
            }

            $match->delete();
        });

        return response()->json(['message' => 'Partido eliminado.']);
    }

    /**
     * Accept or reject a pending join request. Accept-always also trusts the player.
     */
    public function reviewPlayer(Request $request, int $id, int $playerId): JsonResponse
    {
        $validated = $request->validate([
            'action' => ['required', 'in:accept_once,accept_always,reject'],
        ]);

        $match = DB::transaction(function () use ($request, $id, $playerId, $validated): MatchModel {
            $match = MatchModel::query()->lockForUpdate()->findOrFail($id);
            $organizerId = $request->user()->getAuthIdentifier();

            if ($match->organizer_id !== $organizerId) {
                abort(403, 'Solo el organizador puede revisar solicitudes.');
            }

            $player = $match->players()->where('user_id', $playerId)->first();
            if ($player === null) {
                abort(404, 'El jugador no está en este partido.');
            }

            if ($player->status !== MatchPlayerStatus::Pending) {
                abort(409, 'Esta solicitud ya fue resuelta.');
            }

            if ($validated['action'] === 'reject') {
                $this->releasePlayer($match, $player);

                return $match->fresh(['players.user', 'organizer', 'sport', 'level', 'court', 'courtFields.sports']);
            }

            $player->status = MatchPlayerStatus::Confirmed;
            $player->save();

            if ($validated['action'] === 'accept_always') {
                TrustedPlayer::query()->firstOrCreate([
                    'organizer_id' => $organizerId,
                    'player_id' => $playerId,
                ]);
            }

            return $match->fresh(['players.user', 'organizer', 'sport', 'level', 'court', 'courtFields.sports']);
        });

        return response()->json(['data' => $match]);
    }

    /**
     * Add a player to a match. Only the organizer can do this.
     */
    public function addPlayer(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
        ]);
        $playerId = $validated['user_id'];

        $match = DB::transaction(function () use ($request, $id, $playerId): MatchModel {
            $match = MatchModel::query()->lockForUpdate()->findOrFail($id);
            $organizerId = $request->user()->getAuthIdentifier();

            if ($match->organizer_id !== $organizerId) {
                abort(403, 'Solo el organizador puede agregar jugadores.');
            }

            if ($match->status === MatchStatus::Cancelled) {
                abort(409, 'No se puede agregar jugadores a un partido cancelado.');
            }

            if ($playerId === $organizerId) {
                abort(409, 'El organizador ya forma parte del partido.');
            }

            $existing = $match->players()->where('user_id', $playerId)->first();
            if ($existing !== null) {
                if ($existing->status === MatchPlayerStatus::Confirmed) {
                    abort(409, 'El jugador ya está en este partido.');
                }

                if ($existing->status === MatchPlayerStatus::Reserved) {
                    abort(409, 'El jugador ya está en la reserva.');
                }

                $existing->status = MatchPlayerStatus::Confirmed;
                $existing->save();

                return $match->fresh(['players.user', 'organizer', 'sport', 'level', 'court', 'courtFields.sports']);
            }

            $hasActiveSlot = $match->missing_players >= 1;
            $match->players()->create([
                'user_id' => $playerId,
                'quantity_slots' => 1,
                'status' => $hasActiveSlot
                    ? MatchPlayerStatus::Confirmed
                    : MatchPlayerStatus::Reserved,
            ]);

            if ($hasActiveSlot) {
                $match->missing_players -= 1;
                $match->status = $match->missing_players === 0
                    ? MatchStatus::Full
                    : MatchStatus::Open;
                $match->save();
            }

            return $match->fresh(['players.user', 'organizer', 'sport', 'level', 'court', 'courtFields.sports']);
        });

        return response()->json(['data' => $match]);
    }

    /**
     * Remove a player from a match. Only the organizer can do this.
     */
    public function removePlayer(Request $request, int $id, int $playerId): JsonResponse
    {
        $match = DB::transaction(function () use ($request, $id, $playerId): MatchModel {
            $match = MatchModel::query()->lockForUpdate()->findOrFail($id);
            $organizerId = $request->user()->getAuthIdentifier();

            if ($match->organizer_id !== $organizerId) {
                abort(403, 'Solo el organizador puede quitar jugadores.');
            }

            if ($playerId === $organizerId) {
                abort(409, 'El organizador no puede quitarse a sí mismo.');
            }

            $player = $match->players()->where('user_id', $playerId)->first();
            if ($player === null) {
                abort(404, 'El jugador no está en este partido.');
            }

            $this->releasePlayer($match, $player);

            return $match->fresh(['players.user', 'organizer', 'sport', 'level', 'court', 'courtFields.sports']);
        });

        return response()->json(['data' => $match]);
    }

    /**
     * List the rating tags of the match sport. Only the organizer can load them
     * once the match has ended, while finishing it.
     */
    public function ratingTags(Request $request, int $id): JsonResponse
    {
        $match = MatchModel::query()->findOrFail($id);
        $this->assertCanFinish($request, $match);

        $tags = RatingTag::query()
            ->where('sport_id', $match->sport_id)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        return response()->json(['data' => $tags]);
    }

    /**
     * Mark a concluded match as finished. Ratings are optional.
     */
    public function finish(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'ratings' => ['sometimes', 'array'],
            'ratings.*.user_id' => ['required', 'integer', 'distinct'],
            'ratings.*.stars' => ['nullable', 'integer', 'min:1', 'max:5'],
            'ratings.*.did_not_attend' => ['sometimes', 'boolean'],
            'ratings.*.tag_ids' => ['sometimes', 'array'],
            'ratings.*.tag_ids.*' => ['integer', 'distinct'],
        ]);

        $match = DB::transaction(function () use ($request, $id, $validated): MatchModel {
            $match = MatchModel::query()->lockForUpdate()->findOrFail($id);
            $this->assertCanFinish($request, $match);

            $confirmedIds = $match->players()
                ->where('status', MatchPlayerStatus::Confirmed)
                ->where('user_id', '!=', $match->organizer_id)
                ->pluck('user_id');

            $tags = RatingTag::query()
                ->where('sport_id', $match->sport_id)
                ->get()
                ->keyBy('id');

            foreach ($validated['ratings'] ?? [] as $rating) {
                $playerId = (int) $rating['user_id'];
                if (! $confirmedIds->contains($playerId)) {
                    abort(422, 'Solo se puede calificar a jugadores confirmados del partido.');
                }

                $didNotAttend = (bool) ($rating['did_not_attend'] ?? false);
                $stars = $didNotAttend ? null : ($rating['stars'] ?? null);
                $tagIds = $didNotAttend ? [] : collect($rating['tag_ids'] ?? [])->map(fn ($tagId) => (int) $tagId)->unique()->values();

                if (! $didNotAttend && $stars === null && $tagIds->isEmpty()) {
                    continue;
                }

                if (! $didNotAttend && $stars === null) {
                    abort(422, 'Indica las estrellas antes de elegir etiquetas.');
                }

                $selectedTags = collect();
                if (! $didNotAttend && $stars !== null) {
                    $polarity = $stars < 3
                        ? RatingPolarity::Negative
                        : ($stars > 3 ? RatingPolarity::Positive : null);

                    foreach ($tagIds as $tagId) {
                        $tag = $tags->get($tagId);
                        if ($tag === null) {
                            abort(422, 'Una de las etiquetas no pertenece a este deporte.');
                        }

                        if ($polarity === null || $tag->polarity !== $polarity) {
                            abort(422, 'Las etiquetas no coinciden con la calificación.');
                        }

                        $selectedTags->push($tag);
                        if ($tag->marks_absence) {
                            $didNotAttend = true;
                        }
                    }
                }

                $playerRating = PlayerRating::query()->updateOrCreate(
                    [
                        'match_id' => $match->id,
                        'user_id' => $playerId,
                    ],
                    [
                        'reviewer_id' => $match->organizer_id,
                        'stars' => $stars,
                        'did_not_attend' => $didNotAttend,
                    ],
                );
                $playerRating->tags()->sync($selectedTags->pluck('id')->all());
            }

            $match->status = MatchStatus::Finished;
            $match->save();

            return $match->fresh(['players.user', 'organizer', 'sport', 'level', 'court', 'courtFields.sports']);
        });

        return response()->json(['data' => $match]);
    }

    private function assertCanFinish(Request $request, MatchModel $match): void
    {
        if ($match->organizer_id !== $request->user()->getAuthIdentifier()) {
            abort(403, 'Solo el organizador puede terminar este partido.');
        }

        if ($match->status === MatchStatus::Cancelled) {
            abort(409, 'No se puede terminar un partido cancelado.');
        }

        if ($match->status === MatchStatus::Finished) {
            abort(409, 'Este partido ya fue terminado.');
        }

        if ($match->end_time->gt(now())) {
            abort(409, 'El partido todavía no ha concluido.');
        }
    }

    private function releasePlayer(MatchModel $match, MatchPlayer $player): void
    {
        $wasReserved = $player->status === MatchPlayerStatus::Reserved;
        $player->delete();

        if ($wasReserved) {
            return;
        }

        $match->missing_players += $player->quantity_slots;
        $match->status = MatchStatus::Open;
        $match->save();

        $this->promoteReservedPlayers($match);
    }

    /**
     * Move waitlisted players into active slots in arrival order.
     */
    private function promoteReservedPlayers(MatchModel $match): void
    {
        $reservedPlayers = $match->players()
            ->where('status', MatchPlayerStatus::Reserved)
            ->orderBy('created_at')
            ->orderBy('id')
            ->get();

        foreach ($reservedPlayers as $reserved) {
            if ($match->missing_players < $reserved->quantity_slots) {
                break;
            }

            $reserved->status = MatchPlayerStatus::Confirmed;
            $reserved->save();

            $match->missing_players -= $reserved->quantity_slots;
            $match->status = $match->missing_players === 0
                ? MatchStatus::Full
                : MatchStatus::Open;
            $match->save();
        }
    }
}
