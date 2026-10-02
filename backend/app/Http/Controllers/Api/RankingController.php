<?php

namespace App\Http\Controllers\Api;

use App\Enums\MatchPlayerStatus;
use App\Enums\MatchStatus;
use App\Http\Controllers\Controller;
use App\Models\Court;
use App\Models\CourtReservation;
use App\Models\Product;
use App\Models\Store;
use App\Models\StoreOrder;
use App\Models\Team;
use App\Models\TournamentGame;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Public leaderboards for the app "Ranking" screen: sports centers, teams, players, best selling
 * products and stores. Only completed activity counts (paid/confirmed bookings, paid orders,
 * finished matches, played tournament games). `period=month` limits it to the current month.
 */
class RankingController extends Controller
{
    private const LIMIT = 10;

    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'period' => ['sometimes', 'in:month,all'],
            'city_id' => ['sometimes', 'integer', 'exists:cities,id'],
        ]);
        $since = ($validated['period'] ?? 'all') === 'month' ? now()->startOfMonth() : null;
        $cityId = $validated['city_id'] ?? null;

        return response()->json([
            'data' => [
                'period' => $since ? 'month' : 'all',
                'courts' => $this->courts($since, $cityId),
                'products' => $this->products($since, $cityId),
                'stores' => $this->stores($since, $cityId),
                'teams' => $this->teams($since, $cityId),
                'players' => $this->players($since),
            ],
        ]);
    }

    /**
     * Every center, by completed bookings, then rating. Centers without bookings still show
     * (ordered by rating) so the list is useful from day one.
     *
     * @return list<array<string, mixed>>
     */
    private function courts(?Carbon $since, ?int $cityId): array
    {
        $bookings = CourtReservation::query()
            ->join('court_fields', 'court_fields.id', '=', 'court_reservations.court_field_id')
            ->whereIn('court_reservations.status', [CourtReservation::STATUS_PAID, CourtReservation::STATUS_CONFIRMED])
            ->when($since, fn (Builder $query) => $query->where('court_reservations.reserved_on', '>=', $since->toDateString()))
            ->groupBy('court_fields.court_id')
            ->selectRaw('court_fields.court_id as court_id, count(*) as total')
            ->pluck('total', 'court_id');

        return Court::query()
            ->with(['city', 'photos'])
            ->withCount('reviews')
            ->withAvg('reviews', 'rate')
            ->when($cityId, fn (Builder $query) => $query->where('city_id', $cityId))
            ->get()
            ->map(function (Court $court) use ($bookings) {
                [$rating, $count] = $court->ratingSummary();

                return [
                    'id' => $court->id,
                    'name' => $court->name,
                    'subtitle' => $court->city?->name ?? $court->address,
                    'image_url' => $court->photos->first()?->public_url,
                    'bookings' => (int) ($bookings[$court->id] ?? 0),
                    'rating' => $rating,
                    'reviews_count' => $count,
                ];
            })
            ->sortBy([['bookings', 'desc'], ['rating', 'desc'], ['name', 'asc']])
            ->take(self::LIMIT)
            ->values()
            ->all();
    }

    /**
     * Products with paid sales, by units sold.
     *
     * @return list<array<string, mixed>>
     */
    private function products(?Carbon $since, ?int $cityId): array
    {
        $sales = $this->paidItems($since, $cityId)
            ->whereNotNull('store_order_items.product_id')
            ->groupBy('store_order_items.product_id')
            ->select('store_order_items.product_id')
            ->selectRaw('sum(store_order_items.quantity) as units, sum(store_order_items.amount) as revenue')
            ->orderByDesc('units')
            ->limit(self::LIMIT)
            ->get()
            ->keyBy('product_id');

        if ($sales->isEmpty()) {
            return [];
        }

        $products = Product::query()
            ->with(['store', 'photos'])
            ->whereIn('id', $sales->keys())
            ->get()
            ->keyBy('id');

        return $sales
            ->filter(fn ($row) => $products->has($row->product_id))
            ->map(fn ($row) => [
                'id' => $row->product_id,
                'name' => $products[$row->product_id]->name,
                'subtitle' => $products[$row->product_id]->store?->name,
                'image_url' => $products[$row->product_id]->photos->first()?->public_url,
                'units_sold' => (int) $row->units,
                'price' => (float) $products[$row->product_id]->price,
            ])
            ->values()
            ->all();
    }

    /**
     * Stores with paid sales, by number of orders, then units sold.
     *
     * @return list<array<string, mixed>>
     */
    private function stores(?Carbon $since, ?int $cityId): array
    {
        $sales = $this->paidItems($since, $cityId)
            ->groupBy('store_orders.store_id')
            ->select('store_orders.store_id')
            ->selectRaw('count(distinct store_orders.id) as orders, sum(store_order_items.quantity) as units')
            ->orderByDesc('orders')
            ->orderByDesc('units')
            ->limit(self::LIMIT)
            ->get()
            ->keyBy('store_id');

        if ($sales->isEmpty()) {
            return [];
        }

        $stores = Store::query()->with('court')->whereIn('id', $sales->keys())->get()->keyBy('id');

        return $sales
            ->filter(fn ($row) => $stores->has($row->store_id))
            ->map(fn ($row) => [
                'id' => $row->store_id,
                'name' => $stores[$row->store_id]->name,
                'subtitle' => $stores[$row->store_id]->court?->name,
                'image_url' => $stores[$row->store_id]->coverUrl(),
                'orders' => (int) $row->orders,
                'units_sold' => (int) $row->units,
            ])
            ->values()
            ->all();
    }

    /**
     * Teams by points in played tournament games (3 per win, 1 per draw), then goal difference,
     * wins and name. With `city_id`, only games of tournaments held in that city count.
     *
     * @return list<array<string, mixed>>
     */
    private function teams(?Carbon $since, ?int $cityId): array
    {
        $games = TournamentGame::query()
            ->where('status', TournamentGame::STATUS_PLAYED)
            ->whereNotNull('home_score')
            ->whereNotNull('away_score')
            ->whereNotNull('home_team_id')
            ->whereNotNull('away_team_id')
            ->when($since, fn (Builder $query) => $query->where('scheduled_at', '>=', $since))
            ->when($cityId, fn (Builder $query) => $query->whereHas('tournament.court', fn (Builder $court) => $court->where('city_id', $cityId)))
            ->get(['home_team_id', 'away_team_id', 'home_score', 'away_score']);

        $rows = [];
        foreach ($games as $game) {
            foreach ([[$game->home_team_id, $game->home_score, $game->away_score], [$game->away_team_id, $game->away_score, $game->home_score]] as [$teamId, $for, $against]) {
                $row = $rows[$teamId] ?? ['played' => 0, 'won' => 0, 'drawn' => 0, 'lost' => 0, 'goal_difference' => 0, 'points' => 0];
                $row['played']++;
                $row['goal_difference'] += $for - $against;
                if ($for > $against) {
                    $row['won']++;
                    $row['points'] += 3;
                } elseif ($for === $against) {
                    $row['drawn']++;
                    $row['points']++;
                } else {
                    $row['lost']++;
                }
                $rows[$teamId] = $row;
            }
        }

        if ($rows === []) {
            return [];
        }

        $teams = Team::query()->with('sport')->whereIn('id', array_keys($rows))->get()->keyBy('id');

        return collect($rows)
            ->filter(fn ($row, $teamId) => $teams->has($teamId))
            ->map(fn ($row, $teamId) => [
                'id' => (int) $teamId,
                'name' => $teams[$teamId]->name,
                'subtitle' => $teams[$teamId]->sport?->name,
                'image_url' => $teams[$teamId]->logo_url,
                'short_name' => $teams[$teamId]->short_name,
                'color' => $teams[$teamId]->primary_color,
                ...$row,
            ])
            ->sortBy([['points', 'desc'], ['goal_difference', 'desc'], ['won', 'desc'], ['name', 'asc']])
            ->take(self::LIMIT)
            ->values()
            ->all();
    }

    /**
     * Players by matches played: finished casual matches (confirmed) plus played tournament games
     * of their teams. Ties go to the average stars from organizers.
     *
     * @return list<array<string, mixed>>
     */
    private function players(?Carbon $since): array
    {
        $casual = DB::table('match_players')
            ->join('matches', 'matches.id', '=', 'match_players.match_id')
            ->where('match_players.status', MatchPlayerStatus::Confirmed->value)
            ->where('matches.status', MatchStatus::Finished->value)
            ->whereNull('matches.deleted_at')
            ->when($since, fn ($query) => $query->where('matches.start_time', '>=', $since))
            ->groupBy('match_players.user_id')
            ->selectRaw('match_players.user_id as user_id, count(*) as total')
            ->pluck('total', 'user_id');

        $tournament = DB::table('team_members')
            ->join('tournament_games', fn ($join) => $join
                ->on('tournament_games.home_team_id', '=', 'team_members.team_id')
                ->orOn('tournament_games.away_team_id', '=', 'team_members.team_id'))
            ->where('tournament_games.status', TournamentGame::STATUS_PLAYED)
            ->when($since, fn ($query) => $query->where('tournament_games.scheduled_at', '>=', $since))
            ->groupBy('team_members.user_id')
            ->selectRaw('team_members.user_id as user_id, count(*) as total')
            ->pluck('total', 'user_id');

        $played = collect($casual->keys())->merge($tournament->keys())->unique()
            ->mapWithKeys(fn ($userId) => [$userId => (int) ($casual[$userId] ?? 0) + (int) ($tournament[$userId] ?? 0)]);

        if ($played->isEmpty()) {
            return [];
        }

        $ratings = DB::table('player_ratings')
            ->whereIn('user_id', $played->keys())
            ->whereNotNull('stars')
            ->groupBy('user_id')
            ->select('user_id')
            ->selectRaw('avg(stars) as average, count(*) as total')
            ->get()
            ->keyBy('user_id');

        $users = User::query()->whereIn('id', $played->keys())->get()->keyBy('id');

        return $played
            ->map(fn ($matches, $userId) => [
                'id' => (int) $userId,
                'name' => $users[$userId]->nickname ?: $users[$userId]->name,
                'subtitle' => $users[$userId]->preferred_position,
                'image_url' => $users[$userId]->avatar_path ? Storage::disk('public')->url($users[$userId]->avatar_path) : null,
                'matches_played' => (int) $matches,
                'rating' => isset($ratings[$userId]) ? round((float) $ratings[$userId]->average, 1) : null,
                'reviews_count' => (int) ($ratings[$userId]->total ?? 0),
            ])
            ->filter(fn ($row) => $users->has($row['id']))
            ->sortBy([['matches_played', 'desc'], ['rating', 'desc'], ['name', 'asc']])
            ->take(self::LIMIT)
            ->values()
            ->all();
    }

    /**
     * Items of paid store orders, optionally since a date and for the stores of one city.
     */
    private function paidItems(?Carbon $since, ?int $cityId): \Illuminate\Database\Query\Builder
    {
        return DB::table('store_order_items')
            ->join('store_orders', 'store_orders.id', '=', 'store_order_items.store_order_id')
            ->where('store_orders.status', StoreOrder::STATUS_PAID)
            ->when($since, fn ($query) => $query->where('store_orders.paid_at', '>=', $since))
            ->when($cityId, fn ($query) => $query
                ->join('stores', 'stores.id', '=', 'store_orders.store_id')
                ->join('courts', 'courts.id', '=', 'stores.court_id')
                ->where('courts.city_id', $cityId));
    }
}
