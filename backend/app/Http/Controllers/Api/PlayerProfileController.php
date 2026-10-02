<?php

namespace App\Http\Controllers\Api;

use App\Enums\MatchPlayerStatus;
use App\Enums\MatchStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\MatchModel;
use App\Models\PlayerRating;
use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Public player profile (summary, stats and reputation) and editing your own profile.
 */
class PlayerProfileController extends Controller
{
    /**
     * Profile shown when tapping a player. Email and phone are only included for yourself.
     */
    public function show(Request $request, User $user): JsonResponse
    {
        $isMe = $request->user()->id === $user->id;
        $played = $this->playedMatches($user)->with(['sport:id,name', 'court:id,name'])->get();

        $ratings = PlayerRating::query()->where('user_id', $user->id);
        $starred = (clone $ratings)->whereNotNull('stars');
        $totalRatings = (clone $ratings)->count();
        $noShows = (clone $ratings)->where('did_not_attend', true)->count();
        $distribution = (clone $starred)
            ->select('stars', DB::raw('count(*) as total'))
            ->groupBy('stars')
            ->pluck('total', 'stars');

        $topTags = DB::table('player_rating_tag')
            ->join('player_ratings', 'player_ratings.id', '=', 'player_rating_tag.player_rating_id')
            ->join('rating_tags', 'rating_tags.id', '=', 'player_rating_tag.rating_tag_id')
            ->where('player_ratings.user_id', $user->id)
            ->where('rating_tags.marks_absence', false)
            ->select('rating_tags.label', 'rating_tags.polarity', DB::raw('count(*) as total'))
            ->groupBy('rating_tags.label', 'rating_tags.polarity')
            ->orderByDesc('total')
            ->orderBy('rating_tags.label')
            ->limit(6)
            ->get();

        $teams = Team::query()
            ->withMember($user->id)
            ->with('sport:id,name')
            ->withCount('members')
            ->orderBy('name')
            ->get();

        $profile = (new UserResource($user->loadMissing('favoriteSports')))->resolve($request);
        if (! $isMe) {
            unset($profile['email'], $profile['phone']);
        }

        return response()->json([
            'data' => [
                'user' => $profile,
                'is_me' => $isMe,
                'age' => $user->birth_date?->age,
                'stats' => [
                    'matches_played' => $played->count(),
                    'courts_played' => $played->pluck('court_id')->unique()->count(),
                    'hours_played' => round($played->sum(fn (MatchModel $match) => $match->start_time->diffInMinutes($match->end_time)) / 60, 1),
                    'matches_organized' => MatchModel::query()
                        ->where('organizer_id', $user->id)
                        ->where('status', '!=', MatchStatus::Cancelled->value)
                        ->count(),
                    'upcoming_matches' => MatchModel::query()
                        ->where('start_time', '>', now())
                        ->whereIn('status', [MatchStatus::Open->value, MatchStatus::Full->value])
                        ->whereHas('players', fn (Builder $players) => $players
                            ->where('user_id', $user->id)
                            ->where('status', MatchPlayerStatus::Confirmed->value))
                        ->count(),
                    'sports' => $played
                        ->groupBy('sport_id')
                        ->map(fn ($matches) => ['name' => $matches->first()->sport?->name, 'matches' => $matches->count()])
                        ->sortByDesc('matches')
                        ->values(),
                ],
                'rating' => [
                    'average' => $starred->count() > 0 ? round((float) (clone $starred)->avg('stars'), 1) : null,
                    'count' => (clone $starred)->count(),
                    'distribution' => collect([5, 4, 3, 2, 1])->mapWithKeys(fn (int $stars) => [$stars => (int) ($distribution[$stars] ?? 0)]),
                    'no_shows' => $noShows,
                    // Share of rated matches the player showed up to.
                    'attendance_rate' => $totalRatings > 0 ? round(($totalRatings - $noShows) / $totalRatings * 100) : null,
                ],
                'top_tags' => $topTags->map(fn ($tag) => [
                    'label' => $tag->label,
                    'polarity' => $tag->polarity,
                    'count' => (int) $tag->total,
                ]),
                'teams' => $teams->map(fn (Team $team) => [
                    'id' => $team->id,
                    'name' => $team->name,
                    'short_name' => $team->short_name,
                    'primary_color' => $team->primary_color,
                    'logo_url' => $team->logo_url,
                    'members_count' => $team->members_count,
                    'is_captain' => $team->owner_id === $user->id,
                    'sport' => $team->sport ? ['id' => $team->sport->id, 'name' => $team->sport->name] : null,
                ]),
                'recent_matches' => $played
                    ->sortByDesc('start_time')
                    ->take(5)
                    ->map(fn (MatchModel $match) => [
                        'id' => $match->id,
                        'start_time' => $match->start_time->toIso8601String(),
                        'sport' => $match->sport?->name,
                        'court' => $match->court?->name,
                    ])
                    ->values(),
            ],
        ]);
    }

    /**
     * Edit your own profile (the fields shown to other players).
     */
    public function update(Request $request): JsonResponse
    {
        $user = $request->user();
        $validated = $request->validate([
            'favorite_sport_ids' => ['sometimes', 'array'],
            'favorite_sport_ids.*' => ['integer', 'distinct', 'exists:sports,id'],
        ]);
        if (array_key_exists('favorite_sport_ids', $validated)) {
            $user->favoriteSports()->sync($validated['favorite_sport_ids']);
        }
        $user->update($request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'nickname' => ['nullable', 'string', 'max:40'],
            'phone' => ['nullable', 'string', 'max:30'],
            'gender' => ['sometimes', 'required', Rule::in(['male', 'female'])],
            'preferred_position' => ['nullable', 'string', 'max:60'],
            'birth_date' => ['nullable', 'date', 'before:-5 years', 'after:1900-01-01'],
        ], [
            'birth_date.before' => 'Revisa la fecha de nacimiento.',
            'birth_date.after' => 'Revisa la fecha de nacimiento.',
        ], [
            'name' => 'nombre',
            'phone' => 'teléfono',
            'gender' => 'sexo',
            'preferred_position' => 'posición',
            'birth_date' => 'fecha de nacimiento',
        ]));

        return response()->json(['data' => new UserResource($user->fresh('favoriteSports'))]);
    }

    /**
     * Matches the user played: confirmed in a match that already ended and was not cancelled.
     *
     * @return Builder<MatchModel>
     */
    private function playedMatches(User $user): Builder
    {
        return MatchModel::query()
            ->where('end_time', '<', now())
            ->where('status', '!=', MatchStatus::Cancelled->value)
            ->whereHas('players', fn (Builder $players) => $players
                ->where('user_id', $user->id)
                ->where('status', MatchPlayerStatus::Confirmed->value));
    }
}
