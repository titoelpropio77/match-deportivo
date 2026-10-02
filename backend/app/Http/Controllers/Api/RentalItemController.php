<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\RentalItemResource;
use App\Models\Court;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RentalItemController extends Controller
{
    /**
     * Active gear a sports center rents with its courts, optionally for one sport.
     */
    public function index(Request $request, Court $court): JsonResponse
    {
        $validated = $request->validate([
            'sport_id' => ['nullable', 'integer', 'exists:sports,id'],
        ]);

        $items = $court->rentalItems()
            ->active()
            ->with('sport')
            ->when(isset($validated['sport_id']), fn ($query) => $query->where('sport_id', $validated['sport_id']))
            ->orderBy('name')
            ->get();

        return response()->json(['data' => RentalItemResource::collection($items)]);
    }
}
