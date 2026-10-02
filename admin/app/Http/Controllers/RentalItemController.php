<?php

namespace App\Http\Controllers;

use App\Http\Requests\Courts\RentalItemRequest;
use App\Models\Court;
use App\Models\RentalItem;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

/**
 * Sports gear a venue rents with its courts, managed from the venue edit screen.
 */
class RentalItemController extends Controller
{
    public function store(RentalItemRequest $request, Court $court): RedirectResponse
    {
        $item = $court->rentalItems()->create($request->validated());

        return redirect()->route('courts.edit', $court)->with('success', "Artículo {$item->name} agregado.");
    }

    public function update(RentalItemRequest $request, Court $court, RentalItem $rentalItem): RedirectResponse
    {
        $rentalItem->update($request->validated());

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
}
