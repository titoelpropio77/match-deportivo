<?php

namespace App\Http\Controllers;

use App\Http\Requests\Courts\CourtManagerRequest;
use App\Models\Court;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/**
 * Managers of a venue, assigned by its owner (or platform staff) from the venue edit screen.
 * Assigning grants the "manager" role; removing someone from their last venue takes it away.
 */
class CourtManagerController extends Controller
{
    public function store(CourtManagerRequest $request, Court $court): RedirectResponse
    {
        $user = $request->manager();

        DB::transaction(function () use ($court, $user): void {
            $court->managers()->attach($user->id);
            if (! $user->hasRole('manager')) {
                $user->assignRole('manager');
            }
        });

        return redirect()->route('courts.edit', $court)->with('success', "{$user->name} ahora es manager de {$court->name}.");
    }

    public function destroy(Court $court, User $manager): RedirectResponse
    {
        Gate::authorize('assignManagers', $court);

        DB::transaction(function () use ($court, $manager): void {
            $court->managers()->detach($manager->id);
            if ($manager->hasRole('manager') && ! $manager->managedCourts()->exists()) {
                $manager->removeRole('manager');
            }
        });

        return redirect()->route('courts.edit', $court)->with('success', "{$manager->name} ya no es manager de {$court->name}.");
    }
}
