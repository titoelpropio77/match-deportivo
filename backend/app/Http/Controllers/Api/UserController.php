<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserController extends Controller
{
    /**
     * Search registered users by nickname, name or email.
     */
    public function search(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'query' => ['required', 'string', 'min:1', 'max:255'],
        ]);

        $term = '%'.mb_strtolower($validated['query']).'%';

        $users = User::query()
            ->where(function ($builder) use ($term): void {
                $builder
                    ->whereRaw('LOWER(name) LIKE ?', [$term])
                    ->orWhereRaw('LOWER(nickname) LIKE ?', [$term])
                    ->orWhereRaw('LOWER(email) LIKE ?', [$term]);
            })
            ->orderBy('name')
            ->limit(20)
            ->get();

        return response()->json([
            'data' => UserResource::collection($users),
        ]);
    }
}
