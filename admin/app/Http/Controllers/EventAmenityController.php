<?php

namespace App\Http\Controllers;

use App\DataTables\EventAmenityDataTable;
use App\Http\Requests\EventAmenities\EventAmenityRequest;
use App\Models\EventAmenity;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * Catalog of what event spaces can include (event_amenities), offered in the event space modal.
 */
class EventAmenityController extends Controller
{
    public function index(EventAmenityDataTable $dataTable): mixed
    {
        return $dataTable->render('event-amenities.index');
    }

    public function create(): View
    {
        return view('event-amenities.create', ['amenity' => new EventAmenity]);
    }

    public function store(EventAmenityRequest $request): RedirectResponse
    {
        $amenity = EventAmenity::query()->create($request->validated());

        return redirect()->route('event-amenities.index')->with('success', "Servicio {$amenity->name} creado.");
    }

    public function edit(EventAmenity $eventAmenity): View
    {
        return view('event-amenities.edit', ['amenity' => $eventAmenity]);
    }

    public function update(EventAmenityRequest $request, EventAmenity $eventAmenity): RedirectResponse
    {
        $eventAmenity->update($request->validated());

        return redirect()->route('event-amenities.index')->with('success', "Servicio {$eventAmenity->name} actualizado.");
    }

    public function destroy(EventAmenity $eventAmenity): JsonResponse
    {
        // The pivot rows go with it (cascade): the spaces just stop showing it.
        $eventAmenity->delete();

        return response()->json(['message' => "Servicio {$eventAmenity->name} eliminado."]);
    }
}
