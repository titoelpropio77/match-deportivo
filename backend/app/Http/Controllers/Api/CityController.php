<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\CityResource;
use App\Models\City;
use Illuminate\Http\JsonResponse;

class CityController extends Controller
{
    /**
     * List active cities grouped by department order, for filters and court forms.
     */
    public function index(): JsonResponse
    {
        $cities = City::query()
            ->active()
            ->orderBy('department')
            ->orderBy('name')
            ->get();

        return response()->json([
            'data' => CityResource::collection($cities),
        ]);
    }
}
