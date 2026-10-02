<?php

namespace App\Http\Controllers\Api;

use App\Enums\MatchGender;
use App\Enums\TeamRole;
use App\Http\Controllers\Controller;
use App\Http\Resources\TeamResource;
use App\Models\Team;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Teams of players. The owner (captain) edits the team and manages its members; members can leave.
 */
class TeamController extends Controller
{
    /**
     * Teams the current user plays in, optionally of one sport.
     */
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate(['sport_id' => ['nullable', 'integer', 'exists:sports,id']]);

        $teams = Team::query()
            ->withMember($request->user()->id)
            ->with(['sport', 'level'])
            ->withCount('members')
            ->when($validated['sport_id'] ?? null, fn ($query, $sportId) => $query->where('sport_id', $sportId))
            ->orderBy('name')
            ->get();

        return response()->json(['data' => TeamResource::collection($teams)]);
    }

    /**
     * Any team by name or abbreviation (e.g. to invite a rival team to a match).
     */
    public function search(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'query' => ['nullable', 'string', 'max:60'],
            'sport_id' => ['nullable', 'integer', 'exists:sports,id'],
        ]);
        $term = trim($validated['query'] ?? '');

        $teams = Team::query()
            ->with(['sport', 'level'])
            ->withCount('members')
            ->when($validated['sport_id'] ?? null, fn ($query, $sportId) => $query->where('sport_id', $sportId))
            ->when($term !== '', fn ($query) => $query->search($term))
            ->orderBy('name')
            ->limit(20)
            ->get();

        return response()->json(['data' => TeamResource::collection($teams)]);
    }

    public function show(Team $team): JsonResponse
    {
        return response()->json(['data' => new TeamResource($this->loadDetail($team))]);
    }

    /**
     * Create a team; the creator becomes its captain. `member_ids` adds players right away.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $this->validateTeam($request);
        $validated += $request->validate([
            'member_ids' => ['sometimes', 'array', 'max:'.(Team::MAX_MEMBERS - 1)],
            'member_ids.*' => ['integer', 'distinct', 'exists:users,id'],
        ]);
        $owner = $request->user();

        $team = DB::transaction(function () use ($validated, $owner, $request): Team {
            $team = Team::query()->create([
                ...collect($validated)->except(['logo', 'member_ids', 'remove_logo'])->all(),
                'owner_id' => $owner->id,
                'logo_path' => $request->file('logo')?->store('team-logos', 'public'),
            ]);

            $team->members()->create(['user_id' => $owner->id, 'role' => TeamRole::Captain]);
            foreach (collect($validated['member_ids'] ?? [])->reject(fn ($id) => $id === $owner->id) as $userId) {
                $team->members()->create(['user_id' => $userId, 'role' => TeamRole::Player]);
            }

            return $team;
        });

        return response()->json(['data' => new TeamResource($this->loadDetail($team))], 201);
    }

    /**
     * Update the team details (multipart, so it is a POST). `remove_logo=1` deletes the logo.
     */
    public function update(Request $request, Team $team): JsonResponse
    {
        $this->authorizeOwner($request, $team);
        $validated = $this->validateTeam($request, updating: true);

        $previousLogo = $team->logo_path;
        $attributes = collect($validated)->except(['logo', 'remove_logo'])->all();
        if ($request->hasFile('logo')) {
            $attributes['logo_path'] = $request->file('logo')->store('team-logos', 'public');
        } elseif ($request->boolean('remove_logo')) {
            $attributes['logo_path'] = null;
        }

        $team->update($attributes);
        if ($previousLogo && $previousLogo !== $team->logo_path) {
            (new Team(['logo_path' => $previousLogo]))->deleteLogo();
        }

        return response()->json(['data' => new TeamResource($this->loadDetail($team))]);
    }

    public function destroy(Request $request, Team $team): JsonResponse
    {
        $this->authorizeOwner($request, $team);

        $team->delete();
        $team->deleteLogo();

        return response()->json(null, 204);
    }

    public function addMember(Request $request, Team $team): JsonResponse
    {
        $this->authorizeOwner($request, $team);
        $validated = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'jersey_number' => ['nullable', 'integer', 'between:0,99'],
            'position' => ['nullable', 'string', 'max:40'],
        ], [], ['jersey_number' => 'número de camiseta', 'position' => 'posición']);

        if ($team->members()->where('user_id', $validated['user_id'])->exists()) {
            throw ValidationException::withMessages(['user_id' => 'Ese jugador ya es parte del equipo.']);
        }
        if ($team->members()->count() >= Team::MAX_MEMBERS) {
            throw ValidationException::withMessages(['user_id' => 'Un equipo puede tener como máximo '.Team::MAX_MEMBERS.' jugadores.']);
        }

        $team->members()->create([...$validated, 'role' => TeamRole::Player]);

        return response()->json(['data' => new TeamResource($this->loadDetail($team))], 201);
    }

    /**
     * Jersey number and position of a member (owner, or the member for themselves).
     */
    public function updateMember(Request $request, Team $team, User $user): JsonResponse
    {
        $member = $team->members()->where('user_id', $user->id)->firstOrFail();
        abort_unless($request->user()->id === $team->owner_id || $request->user()->id === $user->id, 403);

        $member->update($request->validate([
            'jersey_number' => ['nullable', 'integer', 'between:0,99'],
            'position' => ['nullable', 'string', 'max:40'],
        ], [], ['jersey_number' => 'número de camiseta', 'position' => 'posición']));

        return response()->json(['data' => new TeamResource($this->loadDetail($team))]);
    }

    /**
     * The owner removes a member, or a member leaves. The owner cannot leave their own team.
     */
    public function removeMember(Request $request, Team $team, User $user): JsonResponse
    {
        $actor = $request->user();
        abort_unless($actor->id === $team->owner_id || $actor->id === $user->id, 403);

        if ($user->id === $team->owner_id) {
            throw ValidationException::withMessages([
                'user_id' => 'El capitán no puede salir del equipo. Elimina el equipo si ya no lo usarán.',
            ]);
        }

        $team->members()->where('user_id', $user->id)->delete();

        return response()->json(['data' => new TeamResource($this->loadDetail($team))]);
    }

    /**
     * @return array<string, mixed>
     */
    private function validateTeam(Request $request, bool $updating = false): array
    {
        $required = $updating ? 'sometimes' : 'required';

        $validated = $request->validate([
            'name' => [$required, 'string', 'min:2', 'max:60'],
            'short_name' => ['nullable', 'string', 'min:2', 'max:4', 'alpha_num'],
            'sport_id' => [$required, 'integer', 'exists:sports,id'],
            'level_id' => ['nullable', 'integer', 'exists:match_levels,id'],
            'gender' => ['sometimes', Rule::enum(MatchGender::class)],
            'primary_color' => ['nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'description' => ['nullable', 'string', 'max:500'],
            'logo' => ['nullable', 'image', 'mimes:jpeg,jpg,png,webp', 'max:4096'],
            'remove_logo' => ['sometimes', 'boolean'],
        ], [
            'logo.image' => 'El logo debe ser una imagen.',
            'logo.max' => 'El logo puede pesar como máximo 4 MB.',
            'primary_color.regex' => 'El color debe tener el formato #RRGGBB.',
        ], [
            'name' => 'nombre',
            'short_name' => 'abreviatura',
            'sport_id' => 'deporte',
            'level_id' => 'nivel',
            'gender' => 'categoría',
            'primary_color' => 'color',
            'description' => 'descripción',
        ]);

        // Abbreviations are shown in capitals ("TIG").
        if (isset($validated['short_name'])) {
            $validated['short_name'] = mb_strtoupper($validated['short_name']);
        }

        return $validated;
    }

    private function authorizeOwner(Request $request, Team $team): void
    {
        abort_unless($request->user()->id === $team->owner_id, 403, 'Solo el capitán puede modificar el equipo.');
    }

    private function loadDetail(Team $team): Team
    {
        return $team->load([
            'sport',
            'level',
            'members' => fn ($members) => $members
                ->with('user')
                ->orderByRaw("case when role = 'captain' then 0 else 1 end")
                ->orderBy('jersey_number')
                ->orderBy('id'),
        ]);
    }
}
