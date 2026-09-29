<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\CourtResource;
use App\Models\Court;
use Illuminate\Http\JsonResponse;

class CourtController extends Controller
{
    /**
     * List all courts with their sports and photo gallery.
     */
    public function index(): JsonResponse
    {
        $courts = Court::query()
            ->with(['sports', 'photos'])
            ->orderBy('name')
            ->get();

        return response()->json([
            'data' => CourtResource::collection($courts),
        ]);
    }
}
