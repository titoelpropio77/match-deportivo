<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\EventSpaceResource;
use App\Models\CourtField;
use App\Models\EventSpace;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Event spaces (grill areas, halls...) that sports centers rent by the hour. Only active ones are listed.
 */
class EventSpaceController extends Controller
{
    /**
     * Filters: city, sports center and a number of guests the space must fit.
     */
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'city_id' => ['nullable', 'integer', 'exists:cities,id'],
            'court_id' => ['nullable', 'integer', 'exists:courts,id'],
            'guests' => ['nullable', 'integer', 'min:1'],
        ]);

        $spaces = EventSpace::query()
            ->active()
            ->with(['court.city', 'court.photos'])
            ->when(isset($validated['court_id']), fn ($query) => $query->where('court_id', $validated['court_id']))
            ->when(isset($validated['city_id']), fn ($query) => $query
                ->whereHas('court', fn ($court) => $court->where('city_id', $validated['city_id'])))
            ->when(isset($validated['guests']), fn ($query) => $query->where('capacity', '>=', $validated['guests']))
            ->orderBy('price_per_hour')
            ->orderBy('name')
            ->get();

        return response()->json(['data' => EventSpaceResource::collection($spaces)]);
    }

    public function show(EventSpace $eventSpace): JsonResponse
    {
        abort_unless($eventSpace->is_active, 404);

        $eventSpace->load(['court.city', 'court.photos']);

        return response()->json(['data' => new EventSpaceResource($eventSpace)]);
    }

    /**
     * Hourly availability of a space on a date.
     */
    public function availability(Request $request, EventSpace $eventSpace): JsonResponse
    {
        abort_unless($eventSpace->is_active, 404);

        $validated = $request->validate([
            'date' => ['required', 'date_format:Y-m-d'],
        ]);

        $eventSpace->load('court');
        $slots = $eventSpace->slotsForDate($validated['date']);

        return response()->json([
            'data' => [
                'date' => $validated['date'],
                'slots' => $slots,
                'free_ranges' => CourtField::freeRanges($slots),
            ],
        ]);
    }
}
