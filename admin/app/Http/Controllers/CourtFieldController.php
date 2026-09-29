<?php

namespace App\Http\Controllers;

use App\Models\Court;
use App\Models\CourtField;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/**
 * Physical courts (court_fields) managed from the venue edit screen.
 */
class CourtFieldController extends Controller
{
    public function store(Request $request, Court $court): RedirectResponse
    {
        Gate::authorize('manage', $court);
        $validated = $this->validateField($request);

        DB::transaction(function () use ($court, $validated): void {
            $field = $court->fields()->create($validated);
            $field->sports()->sync($validated['sports']);
        });

        return redirect()->route('courts.edit', $court)->with('success', "Cancha física {$validated['name']} agregada.");
    }

    public function update(Request $request, Court $court, CourtField $field): RedirectResponse
    {
        Gate::authorize('manage', $court);
        $validated = $this->validateField($request);

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

    /**
     * @return array<string, mixed>
     */
    private function validateField(Request $request): array
    {
        return $request->validateWithBag('field', [
            'name' => ['required', 'string', 'max:255'],
            'price_per_hour' => ['required', 'numeric', 'min:0', 'max:999999'],
            'sports' => ['required', 'array', 'min:1'],
            'sports.*' => ['integer', 'exists:sports,id'],
        ], [], [
            'name' => 'nombre',
            'price_per_hour' => 'precio por hora',
            'sports' => 'deportes',
        ]);
    }
}
