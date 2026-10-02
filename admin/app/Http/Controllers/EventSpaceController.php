<?php

namespace App\Http\Controllers;

use App\Http\Requests\Courts\EventSpaceRequest;
use App\Models\Court;
use App\Models\CourtPhoto;
use App\Models\EventSpace;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

/**
 * Event spaces (grill areas, halls...) managed from the venue edit screen.
 */
class EventSpaceController extends Controller
{
    public function store(EventSpaceRequest $request, Court $court): RedirectResponse
    {
        $space = $court->eventSpaces()->create($request->spaceData());
        $space->amenities()->sync($request->amenityIds());
        if ($request->hasFile('photo')) {
            $space->update(['photo_path' => $request->file('photo')->store("event-spaces/{$space->id}", CourtPhoto::DISK)]);
        }

        return redirect()->route('courts.edit', $court)->with('success', "Espacio {$space->name} agregado.");
    }

    public function update(EventSpaceRequest $request, Court $court, EventSpace $eventSpace): RedirectResponse
    {
        $previousPhoto = $eventSpace->photo_path;
        $attributes = $request->spaceData();
        if ($request->hasFile('photo')) {
            $attributes['photo_path'] = $request->file('photo')->store("event-spaces/{$eventSpace->id}", CourtPhoto::DISK);
        } elseif ($request->boolean('remove_photo')) {
            $attributes['photo_path'] = null;
        }

        $eventSpace->update($attributes);
        $eventSpace->amenities()->sync($request->amenityIds());
        if ($previousPhoto !== $eventSpace->photo_path) {
            EventSpace::deleteStoredPhoto($previousPhoto);
        }

        return redirect()->route('courts.edit', $court)->with('success', "Espacio {$eventSpace->name} actualizado.");
    }

    /**
     * Spaces with upcoming bookings cannot be deleted: they can be deactivated instead.
     */
    public function destroy(Court $court, EventSpace $eventSpace): RedirectResponse
    {
        Gate::authorize('manage', $court);

        $upcoming = $eventSpace->reservations()
            ->blocking()
            ->whereDate('reserved_on', '>=', today())
            ->exists();
        if ($upcoming) {
            return redirect()->route('courts.edit', $court)
                ->with('error', "{$eventSpace->name} tiene reservas próximas. Desactívalo para que no se pueda reservar más.");
        }

        $eventSpace->deletePhoto();
        $eventSpace->delete();

        return redirect()->route('courts.edit', $court)->with('success', "Espacio {$eventSpace->name} eliminado.");
    }
}
