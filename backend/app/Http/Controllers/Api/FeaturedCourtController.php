<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Court;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * Sports centers highlighted on the app home: best rated first, then recently added ones.
 * When none qualifies, any center is returned so the section is never empty while centers exist.
 */
class FeaturedCourtController extends Controller
{
    /** A center counts as "new" during this many days after it was added. */
    public const NEW_DAYS = 30;

    /** Minimum average (1-5) to be shown as "best rated". */
    public const TOP_RATED_MIN = 4.0;

    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'city_id' => ['sometimes', 'integer', 'exists:cities,id'],
            'limit' => ['sometimes', 'integer', 'min:1', 'max:20'],
        ]);
        $limit = $validated['limit'] ?? 10;

        $courts = Court::query()
            ->with(['city', 'photos', 'sports'])
            ->withCount('reviews')
            ->withAvg('reviews', 'rate')
            ->withMin('fields', 'price_per_hour')
            ->when(isset($validated['city_id']), fn ($query) => $query->where('city_id', $validated['city_id']))
            ->get()
            ->map(fn (Court $court) => $this->present($court));

        $topRated = $courts
            ->filter(fn (array $court) => $court['reviews_count'] > 0 && $court['rating'] >= self::TOP_RATED_MIN)
            ->sortBy([['rating', 'desc'], ['reviews_count', 'desc']])
            ->map(fn (array $court) => [...$court, 'highlight' => 'top_rated']);

        $new = $courts
            ->filter(fn (array $court) => $court['is_new'])
            ->sortByDesc('created_at')
            ->map(fn (array $court) => [...$court, 'highlight' => 'new']);

        $featured = $topRated->concat($new)->unique('id')->values();

        if ($featured->isEmpty()) {
            // Fallback: any center, best known first, so users always see something to book.
            $featured = $courts->sortBy([['rating', 'desc'], ['name', 'asc']])->values();
        }

        return response()->json([
            'data' => $featured->take($limit)->map(fn (array $court) => collect($court)->except('created_at')->all())->values(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function present(Court $court): array
    {
        [$rating, $count] = $court->ratingSummary();

        return [
            'id' => $court->id,
            'name' => $court->name,
            'address' => $court->address,
            'city' => $court->city?->name,
            'photo_url' => $court->photos->first()?->public_url,
            'rating' => $rating,
            'reviews_count' => $count,
            'min_price' => $court->fields_min_price_per_hour !== null ? (float) $court->fields_min_price_per_hour : null,
            'sports' => $court->sports->pluck('name')->values()->all(),
            'is_new' => $court->created_at !== null && $court->created_at->gte(now()->subDays(self::NEW_DAYS)),
            'highlight' => null,
            'created_at' => $court->created_at,
        ];
    }
}
