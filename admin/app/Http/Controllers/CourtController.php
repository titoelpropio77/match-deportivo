<?php

namespace App\Http\Controllers;

use App\DataTables\CourtDataTable;
use App\Http\Requests\Courts\CourtRequest;
use App\Models\City;
use App\Models\Court;
use App\Models\CourtFeature;
use App\Models\CourtPhoto;
use App\Models\EventAmenity;
use App\Models\Sport;
use App\Models\StoreOrder;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class CourtController extends Controller
{
    public function index(CourtDataTable $dataTable): mixed
    {
        return $dataTable->render('courts.index');
    }

    public function create(): View
    {
        return view('courts.create', [
            'court' => new Court(['opening_time' => '08:00', 'closing_time' => '23:00']),
            ...$this->formOptions(),
        ]);
    }

    public function store(CourtRequest $request): RedirectResponse
    {
        $validated = $request->validated();

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

        $court->load([
            'owner', 'city', 'sports', 'photos', 'managers', 'fields.sports', 'fields.features', 'eventSpaces.amenities', 'rentalItems.sport',
            'stores' => fn ($stores) => $stores->with('categories')->withCount('products'),
        ])->loadCount('matches');

        return view('courts.show', ['court' => $court]);
    }

    public function edit(Court $court): View
    {
        Gate::authorize('manage', $court);

        $court->load(['owner', 'sports', 'photos', 'managers', 'fields.sports', 'fields.features', 'eventSpaces.amenities', 'rentalItems.sport']);

        return view('courts.edit', [
            'court' => $court,
            // Options of the physical court modal (court_features catalog).
            'fieldFeatures' => CourtFeature::query()->orderBy('name')->get(),
            // Options of the event space modal (event_amenities catalog).
            'spaceAmenities' => EventAmenity::query()->orderBy('name')->get(),
            ...$this->formOptions($court),
        ]);
    }

    public function update(CourtRequest $request, Court $court): RedirectResponse
    {
        $validated = $request->validated();

        // Only users who see every court can reassign the owner.
        if (! $request->user()->can('courts.view_all')) {
            unset($validated['owner_id']);
        }

        // CourtRequest already checked that the gallery stays within Court::MAX_PHOTOS.
        $removed = $court->photos()->whereIn('id', $validated['remove_photos'] ?? [])->get();
        $newPhotos = $request->file('photos', []);

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

        // Stores cascade with the venue: their sales history must not be lost.
        if (StoreOrder::query()->whereHas('store', fn ($store) => $store->where('court_id', $court->id))->exists()) {
            return response()->json([
                'message' => 'No se puede eliminar: las tiendas del centro deportivo tienen ventas registradas.',
            ], 422);
        }

        $photos = $court->photos()->get();
        $court->delete();
        $photos->each->deleteFile();

        return response()->json(['message' => "Centro deportivo {$court->name} eliminado."]);
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
