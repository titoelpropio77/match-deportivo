<?php

namespace App\Http\Controllers;

use App\Models\Court;
use App\Models\CourtField;
use App\Models\CourtReservation;
use App\Models\MatchModel;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    /**
     * Stats are limited to the courts the user can see (a partner only sees their own).
     */
    public function __invoke(Request $request): View
    {
        $user = $request->user();
        $courtIds = Court::query()->visibleTo($user)->select('id');

        return view('dashboard', [
            'stats' => [
                'users' => $user->can('users.index') ? User::query()->count() : null,
                'courts' => Court::query()->whereIn('id', $courtIds)->count(),
                'fields' => CourtField::query()->whereIn('court_id', $courtIds)->count(),
                'openMatches' => MatchModel::query()
                    ->whereIn('court_id', $courtIds)
                    ->whereIn('status', ['open', 'full'])
                    ->count(),
                'reservations' => CourtReservation::query()
                    ->whereHas('field', fn ($field) => $field->whereIn('court_id', $courtIds))
                    ->where('status', '!=', 'cancelled')
                    ->count(),
            ],
            'latestUsers' => User::query()->with('roles')->latest()->limit(5)->get(),
        ]);
    }
}
