<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\CourtResource;
use App\Models\Court;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CourtController extends Controller
{
    /**
     * List courts with their city, sports and photo gallery, optionally filtered by city.
     */
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'city_id' => ['sometimes', 'integer', 'exists:cities,id'],
        ]);

        $courts = Court::query()
            ->with(['city', 'sports', 'photos'])
            ->when(isset($validated['city_id']), fn ($query) => $query->where('city_id', $validated['city_id']))
            ->orderBy('name')
            ->get();

        return response()->json([
            'data' => CourtResource::collection($courts),
        ]);
    }
}
