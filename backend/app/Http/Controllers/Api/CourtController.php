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
     * List courts with their city, sports, photos and physical courts, optionally filtered by city
     * and searched by name, address or city (`search`, case and accent insensitive; max 20 results).
     */
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'city_id' => ['sometimes', 'integer', 'exists:cities,id'],
            'search' => ['sometimes', 'nullable', 'string', 'max:100'],
        ]);
        $search = trim((string) ($validated['search'] ?? ''));

        $courts = Court::query()
            ->with(['city', 'sports', 'photos', 'fields.sports', 'fields.features'])
            ->when(isset($validated['city_id']), fn ($query) => $query->where('city_id', $validated['city_id']))
            ->when($search !== '', fn ($query) => $query->search($search)->limit(20))
            ->orderBy('name')
            ->get();

        return response()->json([
            'data' => CourtResource::collection($courts),
        ]);
    }
}
