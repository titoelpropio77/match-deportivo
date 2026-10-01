<?php

namespace App\Http\Controllers;

use App\Models\City;
use App\Models\Court;
use App\Models\CourtPhoto;
use App\Models\Sport;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Yajra\DataTables\Facades\DataTables;

class CourtController extends Controller
{
    private const MAX_PHOTOS = 10;

    public function index(): View
    {
        return view('courts.index');
    }

    /**
     * Server-side DataTables source for the courts list.
     */
    public function data(Request $request): JsonResponse
    {
        $query = Court::query()
            ->visibleTo($request->user())
            ->select('courts.*')
            ->with(['owner:id,name', 'city:id,name,department', 'sports:id,name'])
            ->withCount('fields');

        return DataTables::eloquent($query)
            ->addColumn('owner', fn (Court $court) => e($court->owner?->name ?? '—'))
            ->filterColumn('owner', function ($query, $keyword): void {
                $query->whereHas('owner', fn ($owner) => $owner->where('name', 'ilike', "%{$keyword}%"));
            })
            ->addColumn('city', fn (Court $court) => $court->city
                ? e($court->city->name).'<br><small class="text-muted">'.e($court->city->department).'</small>'
                : '<span class="text-muted">—</span>')
            ->filterColumn('city', function ($query, $keyword): void {
                $query->whereHas('city', fn ($city) => $city
                    ->where('name', 'ilike', "%{$keyword}%")
                    ->orWhere('department', 'ilike', "%{$keyword}%"));
            })
            ->addColumn('sports', fn (Court $court) => $court->sports
                ->map(fn (Sport $sport) => '<span class="badge badge-info mr-1">'.e($sport->name).'</span>')
                ->implode(''))
            ->addColumn('schedule', fn (Court $court) => substr($court->opening_time, 0, 5).' – '.substr($court->closing_time, 0, 5))
            ->editColumn('updated_at', fn (Court $court) => $court->updated_at?->diffForHumans())
            ->addColumn('action', fn (Court $court) => view('courts.partials.actions', ['court' => $court])->render())
            ->rawColumns(['city', 'sports', 'action'])
            ->toJson();
    }

    public function create(): View
    {
        return view('courts.create', [
            'court' => new Court(['opening_time' => '08:00', 'closing_time' => '23:00']),
            ...$this->formOptions(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validateCourt($request);

        // Someone limited to their own courts can only create courts for themselves.
        if (! $request->user()->can('courts.view_all')) {
            $validated['owner_id'] = $request->user()->id;
        }

        $court = DB::transaction(function () use ($validated, $request): Court {
            $court = Court::query()->create($validated);
            $court->sports()->sync($validated['sports'] ?? []);
            $this->storePhotos($court, $request->file('photos', []));

            return $court;
        });

        return redirect()->route('courts.edit', $court)
            ->with('success', "Centro deportivo {$court->name} creado. Ahora agrega sus canchas físicas.");
    }

    public function show(Court $court): View
    {
        Gate::authorize('manage', $court);

        $court->load(['owner', 'city', 'sports', 'photos', 'managers', 'fields.sports'])->loadCount('matches');

        return view('courts.show', ['court' => $court]);
    }

    public function edit(Court $court): View
    {
        Gate::authorize('manage', $court);

        $court->load(['owner', 'sports', 'photos', 'managers', 'fields.sports']);

        return view('courts.edit', [
            'court' => $court,
            ...$this->formOptions($court),
        ]);
    }

    public function update(Request $request, Court $court): RedirectResponse
    {
        Gate::authorize('manage', $court);
        $validated = $this->validateCourt($request);

        // Only users who see every court can reassign the owner.
        if (! $request->user()->can('courts.view_all')) {
            unset($validated['owner_id']);
        }

        $removed = $court->photos()->whereIn('id', $validated['remove_photos'] ?? [])->get();
        $newPhotos = $request->file('photos', []);
        if ($court->photos()->count() - $removed->count() + count($newPhotos) > self::MAX_PHOTOS) {
            throw ValidationException::withMessages([
                'photos' => 'La galería admite como máximo '.self::MAX_PHOTOS.' fotos; quita alguna antes de subir más.',
            ]);
        }

        DB::transaction(function () use ($court, $validated, $removed, $newPhotos): void {
            $court->update($validated);
            $court->sports()->sync($validated['sports'] ?? []);
            CourtPhoto::query()->whereKey($removed->modelKeys())->delete();
            $this->storePhotos($court, $newPhotos);
        });
        $removed->each->deleteFile();

        return redirect()->route('courts.index')->with('success', "Centro deportivo {$court->name} actualizado.");
    }

    public function destroy(Court $court): JsonResponse
    {
        Gate::authorize('manage', $court);

        // matches.court_id is restrictOnDelete; soft-deleted matches still hold the FK.
        $hasMatches = DB::table('matches')->where('court_id', $court->id)->exists();
        if ($hasMatches) {
            return response()->json([
                'message' => 'No se puede eliminar: el centro deportivo tiene partidos registrados.',
            ], 422);
        }

        $photos = $court->photos()->get();
        $court->delete();
        $photos->each->deleteFile();

        return response()->json(['message' => "Centro deportivo {$court->name} eliminado."]);
    }

    /**
     * @return array<string, mixed>
     */
    private function validateCourt(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'city_id' => ['required', 'integer', 'exists:cities,id'],
            'address' => ['required', 'string', 'max:255'],
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'opening_time' => ['required', 'date_format:H:i'],
            'closing_time' => ['required', 'date_format:H:i', 'after:opening_time'],
            'owner_id' => ['nullable', 'integer', 'exists:users,id'],
            'sports' => ['sometimes', 'array'],
            'sports.*' => ['integer', 'exists:sports,id'],
            'photos' => ['sometimes', 'array', 'max:'.self::MAX_PHOTOS],
            'photos.*' => ['image', 'mimes:jpeg,jpg,png,webp', 'max:5120'],
            'remove_photos' => ['sometimes', 'array'],
            'remove_photos.*' => ['integer'],
        ], [
            'photos.*.image' => 'Cada archivo de la galería debe ser una imagen.',
            'photos.*.mimes' => 'Las fotos deben ser JPG, PNG o WEBP.',
            'photos.*.max' => 'Cada foto puede pesar como máximo 5 MB.',
            'photos.*.uploaded' => 'No se pudo subir una de las fotos (¿pesa más de 5 MB?).',
        ], [
            'name' => 'nombre',
            'city_id' => 'ciudad',
            'address' => 'dirección',
            'latitude' => 'latitud',
            'longitude' => 'longitud',
            'opening_time' => 'hora de apertura',
            'closing_time' => 'hora de cierre',
            'owner_id' => 'partner (dueño)',
            'sports' => 'deportes',
            'photos' => 'galería de fotos',
        ]);
    }

    /**
     * Saves the uploads on the backend's public disk, appended after the existing photos.
     *
     * @param  array<int, UploadedFile>  $files
     */
    private function storePhotos(Court $court, array $files): void
    {
        $order = (int) $court->photos()->max('order');

        foreach ($files as $file) {
            $court->photos()->create([
                'url' => $file->store("courts/{$court->id}", CourtPhoto::DISK),
                'order' => ++$order,
            ]);
        }
    }

    /**
     * Owners are partners (role partner), plus the current owner if it no longer has that role.
     *
     * @return array{sports: Collection<int, Sport>, owners: Collection<int, User>, cities: \Illuminate\Support\Collection<string, Collection<int, City>>}
     */
    private function formOptions(?Court $court = null): array
    {
        $owners = User::query()
            ->role('partner')
            ->when($court?->owner_id, fn ($query) => $query->orWhere('id', $court->owner_id))
            ->orderBy('name')
            ->get(['id', 'name', 'email']);

        return [
            'sports' => Sport::query()->orderBy('name')->get(),
            'owners' => $owners,
            'cities' => City::query()
                ->where('is_active', true)
                ->when($court?->city_id, fn ($query) => $query->orWhere('id', $court->city_id))
                ->orderBy('department')
                ->orderBy('name')
                ->get()
                ->groupBy('department'),
        ];
    }
}
