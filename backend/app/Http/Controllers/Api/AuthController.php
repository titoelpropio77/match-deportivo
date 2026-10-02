<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\CompleteProfileRequest;
use App\Http\Requests\Api\LoginRequest;
use App\Http\Requests\Api\RegisterRequest;
use App\Http\Requests\Api\SocialLoginRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\SocialAuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    /**
     * Register a new user and return an API token.
     */
    public function register(RegisterRequest $request): JsonResponse
    {
        $avatarPath = $request->hasFile('photo')
            ? $request->file('photo')->store('avatars', 'public')
            : null;

        $user = User::create([
            'name' => $request->validated('name'),
            'email' => $request->validated('email'),
            'phone' => $request->validated('phone'),
            'gender' => $request->validated('gender'),
            'birth_date' => $request->validated('birth_date'),
            'avatar_path' => $avatarPath,
            'password' => Hash::make($request->validated('password')),
            'profile_completed_at' => now(),
        ]);

        $user->favoriteSports()->sync($request->validated('favorite_sport_ids') ?? []);
        $user->load('favoriteSports');

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'user' => new UserResource($user),
            'token' => $token,
        ], 201);
    }

    /**
     * Authenticate a user and return an API token.
     */
    public function login(LoginRequest $request): JsonResponse
    {
        $credentials = $request->validated();

        if (! Auth::attempt($credentials)) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        /** @var User $user */
        $user = Auth::user();
        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'user' => new UserResource($user->load('favoriteSports')),
            'token' => $token,
        ]);
    }

    /**
     * Sign up / log in with Google or Facebook using the access token from the app's native SDK.
     * A new account comes back with `profile_completed: false` until it goes through completeProfile.
     */
    public function socialLogin(SocialLoginRequest $request, string $provider, SocialAuthService $socialAuth): JsonResponse
    {
        [$user, $created] = $socialAuth->resolveUser($provider, $request->validated('access_token'));

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'user' => new UserResource($user->load('favoriteSports')),
            'token' => $token,
            'is_new_user' => $created,
        ], $created ? 201 : 200);
    }

    /**
     * "Completa tu perfil": saves the data the social provider did not give and unlocks the app.
     */
    public function completeProfile(CompleteProfileRequest $request): JsonResponse
    {
        $user = $request->user();
        $data = $request->safe()->except('favorite_sport_ids');

        $user->update([...$data, 'profile_completed_at' => $user->profile_completed_at ?? now()]);
        if ($request->has('favorite_sport_ids')) {
            $user->favoriteSports()->sync($request->validated('favorite_sport_ids'));
        }

        return response()->json([
            'user' => new UserResource($user->fresh('favoriteSports')),
        ]);
    }

    /**
     * Return the authenticated user for the given token, used to validate an active session.
     */
    public function me(Request $request): JsonResponse
    {
        return response()->json([
            'user' => new UserResource($request->user()->load('favoriteSports')),
        ]);
    }

    /**
     * Revoke the authenticated user's current API token.
     */
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'message' => 'Logged out successfully.',
        ]);
    }
}
