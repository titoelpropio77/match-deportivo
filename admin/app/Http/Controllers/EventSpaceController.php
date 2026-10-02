<?php

namespace App\Http\Controllers;

use App\Enums\EventSpaceAmenity;
use App\Enums\EventSpaceType;
use App\Models\Court;
use App\Models\CourtPhoto;
use App\Models\EventSpace;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * Event spaces (grill areas, halls...) managed from the venue edit screen.
 */
class EventSpaceController extends Controller
{
    public function store(Request $request, Court $court): RedirectResponse
    {
        Gate::authorize('manage', $court);
        $validated = $this->validateSpace($request);

        $space = $court->eventSpaces()->create(collect($validated)->except(['photo', 'remove_photo'])->all());
        if ($request->hasFile('photo')) {
            $space->update(['photo_path' => $request->file('photo')->store("event-spaces/{$space->id}", CourtPhoto::DISK)]);
        }

        return redirect()->route('courts.edit', $court)->with('success', "Espacio {$space->name} agregado.");
    }

    public function update(Request $request, Court $court, EventSpace $eventSpace): RedirectResponse
    {
        Gate::authorize('manage', $court);
        $validated = $this->validateSpace($request);

        $previousPhoto = $eventSpace->photo_path;
        $attributes = collect($validated)->except(['photo', 'remove_photo'])->all();
        if ($request->hasFile('photo')) {
            $attributes['photo_path'] = $request->file('photo')->store("event-spaces/{$eventSpace->id}", CourtPhoto::DISK);
        } elseif ($request->boolean('remove_photo')) {
            $attributes['photo_path'] = null;
        }

        $eventSpace->update($attributes);
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

    /**
     * @return array<string, mixed>
     */
    private function validateSpace(Request $request): array
    {
        $validated = $request->validateWithBag('space', [
            'name' => ['required', 'string', 'max:120'],
            'type' => ['required', Rule::enum(EventSpaceType::class)],
            'description' => ['nullable', 'string', 'max:2000'],
            'price_per_hour' => ['required', 'numeric', 'min:0', 'max:999999'],
            'capacity' => ['required', 'integer', 'between:1,2000'],
            'min_hours' => ['required', 'integer', 'between:1,12'],
            'amenities' => ['sometimes', 'array'],
            'amenities.*' => [Rule::enum(EventSpaceAmenity::class)],
            'rules' => ['nullable', 'string', 'max:2000'],
            'opening_time' => ['nullable', 'date_format:H:i', 'required_with:closing_time'],
            'closing_time' => ['nullable', 'date_format:H:i', 'required_with:opening_time', 'after:opening_time'],
            'is_active' => ['sometimes', 'boolean'],
            'photo' => ['nullable', 'image', 'mimes:jpeg,jpg,png,webp', 'max:5120'],
            'remove_photo' => ['sometimes', 'boolean'],
        ], [
            'amenities.*' => 'Uno de los servicios incluidos no es válido.',
            'closing_time.after' => 'El cierre debe ser posterior a la apertura.',
            'photo.max' => 'La foto puede pesar como máximo 5 MB.',
        ], [
            'name' => 'nombre',
            'type' => 'tipo',
            'description' => 'descripción',
            'price_per_hour' => 'precio por hora',
            'capacity' => 'capacidad',
            'min_hours' => 'mínimo de horas',
            'rules' => 'normas',
            'opening_time' => 'apertura',
            'closing_time' => 'cierre',
            'photo' => 'foto',
        ]);

        // Unchecked checkboxes are not sent: an empty selection must clear the stored values.
        $validated['amenities'] = array_values(array_unique($validated['amenities'] ?? []));
        $validated['is_active'] = $request->boolean('is_active');

        return $validated;
    }
}
