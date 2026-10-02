<?php

namespace App\Http\Controllers;

use App\DataTables\CourtFeatureDataTable;
use App\Http\Requests\CourtFeatures\CourtFeatureRequest;
use App\Models\CourtFeature;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * Catalog of amenities physical courts can have (court_features), offered in the court modal.
 */
class CourtFeatureController extends Controller
{
    public function index(CourtFeatureDataTable $dataTable): mixed
    {
        return $dataTable->render('court-features.index');
    }

    public function create(): View
    {
        return view('court-features.create', ['feature' => new CourtFeature]);
    }

    public function store(CourtFeatureRequest $request): RedirectResponse
    {
        $feature = CourtFeature::query()->create($request->validated());

        return redirect()->route('court-features.index')->with('success', "Característica {$feature->name} creada.");
    }

    public function edit(CourtFeature $courtFeature): View
    {
        return view('court-features.edit', ['feature' => $courtFeature]);
    }

    public function update(CourtFeatureRequest $request, CourtFeature $courtFeature): RedirectResponse
    {
        $courtFeature->update($request->validated());

        return redirect()->route('court-features.index')->with('success', "Característica {$courtFeature->name} actualizada.");
    }

    public function destroy(CourtFeature $courtFeature): JsonResponse
    {
        if ($courtFeature->isSystem()) {
            return response()->json([
                'message' => "No se puede eliminar {$courtFeature->name}: los recargos de las reservas dependen de ella.",
            ], 422);
        }

        // The pivot rows go with it (cascade): the courts just stop showing it.
        $courtFeature->delete();

        return response()->json(['message' => "Característica {$courtFeature->name} eliminada."]);
    }
}
