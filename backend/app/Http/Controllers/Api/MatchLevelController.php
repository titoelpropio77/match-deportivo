<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\MatchLevelResource;
use App\Models\MatchLevel;
use Illuminate\Http\JsonResponse;

class MatchLevelController extends Controller
{
    /**
     * List all available match levels.
     */
    public function index(): JsonResponse
    {
        $levels = MatchLevel::query()->orderBy('order')->get();

        return response()->json([
            'data' => MatchLevelResource::collection($levels),
        ]);
    }
}
