<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\SportResource;
use App\Models\Sport;
use Illuminate\Http\JsonResponse;

class SportController extends Controller
{
    /**
     * List all available sports.
     */
    public function index(): JsonResponse
    {
        $sports = Sport::query()->orderBy('name')->get();

        return response()->json([
            'data' => SportResource::collection($sports),
        ]);
    }
}
