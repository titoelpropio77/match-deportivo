<?php

namespace App\Http\Controllers;

use App\Models\Court;
use App\Models\RentalItem;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * Sports gear a venue rents with its courts, managed from the venue edit screen.
 */
class RentalItemController extends Controller
{
    public function store(Request $request, Court $court): RedirectResponse
    {
        Gate::authorize('manage', $court);

        $item = $court->rentalItems()->create($this->validateItem($request));

        return redirect()->route('courts.edit', $court)->with('success', "Artículo {$item->name} agregado.");
    }

    public function update(Request $request, Court $court, RentalItem $rentalItem): RedirectResponse
    {
        Gate::authorize('manage', $court);

        $rentalItem->update($this->validateItem($request));

        return redirect()->route('courts.edit', $court)->with('success', "Artículo {$rentalItem->name} actualizado.");
    }

    /**
     * Past reservations keep the item's name and price (court_reservation_items copies them).
     */
    public function destroy(Court $court, RentalItem $rentalItem): RedirectResponse
    {
        Gate::authorize('manage', $court);

        $rentalItem->delete();

        return redirect()->route('courts.edit', $court)->with('success', "Artículo {$rentalItem->name} eliminado.");
    }

    /**
     * @return array<string, mixed>
     */
    private function validateItem(Request $request): array
    {
        $validated = $request->validateWithBag('rental', [
            'name' => ['required', 'string', 'max:100'],
            'sport_id' => ['required', 'integer', 'exists:sports,id'],
            'description' => ['nullable', 'string', 'max:500'],
            'price' => ['required', 'numeric', 'min:0', 'max:99999'],
            'price_type' => ['required', Rule::in(array_keys(RentalItem::PRICE_TYPES))],
            'stock' => ['nullable', 'integer', 'between:1,999'],
            'is_active' => ['sometimes', 'boolean'],
        ], [], [
            'name' => 'nombre',
            'sport_id' => 'deporte',
            'description' => 'descripción',
            'price' => 'precio',
            'price_type' => 'tipo de cobro',
            'stock' => 'cantidad disponible',
        ]);
        $validated['is_active'] = $request->boolean('is_active');

        return $validated;
    }
}
