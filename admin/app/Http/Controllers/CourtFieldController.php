<?php

namespace App\Http\Controllers;

use App\Http\Requests\Courts\CourtFieldRequest;
use App\Models\Court;
use App\Models\CourtField;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/**
 * Physical courts (court_fields) managed from the venue edit screen.
 */
class CourtFieldController extends Controller
{
    public function store(CourtFieldRequest $request, Court $court): RedirectResponse
    {
        $validated = $request->validated();

        DB::transaction(function () use ($court, $validated): void {
            $field = $court->fields()->create($validated);
            $field->sports()->sync($validated['sports']);
        });

        return redirect()->route('courts.edit', $court)->with('success', "Cancha física {$validated['name']} agregada.");
    }

    public function update(CourtFieldRequest $request, Court $court, CourtField $field): RedirectResponse
    {
        $validated = $request->validated();

        DB::transaction(function () use ($field, $validated): void {
            $field->update($validated);
            $field->sports()->sync($validated['sports']);
        });

        return redirect()->route('courts.edit', $court)->with('success', "Cancha física {$field->name} actualizada.");
    }

    public function destroy(Court $court, CourtField $field): RedirectResponse
    {
        Gate::authorize('manage', $court);

        $field->delete();

        return redirect()->route('courts.edit', $court)->with('success', "Cancha física {$field->name} eliminada.");
    }
}
