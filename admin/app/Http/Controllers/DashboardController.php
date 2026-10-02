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
        $reservations = fn () => CourtReservation::query()
            ->whereHas('field', fn ($field) => $field->whereIn('court_id', $courtIds));

        return view('dashboard', [
            'stats' => [
                'users' => $user->can('users.index') ? User::query()->count() : null,
                'courts' => Court::query()->whereIn('id', $courtIds)->count(),
                'fields' => CourtField::query()->whereIn('court_id', $courtIds)->count(),
                'openMatches' => MatchModel::query()
                    ->whereIn('court_id', $courtIds)
                    ->whereIn('status', ['open', 'full'])
                    ->count(),
                'reservationsToday' => $reservations()->blocking()->whereDate('reserved_on', today())->count(),
                'monthIncome' => $user->can('reservations.index')
                    ? (float) $reservations()->collected()
                        ->whereBetween('paid_at', [now()->startOfMonth(), now()->endOfMonth()])
                        ->sum('amount')
                    : null,
                'pendingRefunds' => $user->can('reservations.index')
                    ? $reservations()->where('status', CourtReservation::STATUS_CANCELLED)
                        ->whereNotNull('paid_at')->whereNull('refunded_at')->count()
                    : 0,
            ],
            'upcomingReservations' => $user->can('reservations.index')
                ? $reservations()
                    ->with(['field.court:id,name', 'sport:id,name', 'user:id,name'])
                    ->blocking()
                    ->where(fn ($query) => $query
                        ->whereDate('reserved_on', '>', today())
                        ->orWhere(fn ($today) => $today->whereDate('reserved_on', today())
                            ->where('ends_at', '>', now()->format('H:i:s'))))
                    ->orderBy('reserved_on')
                    ->orderBy('starts_at')
                    ->limit(8)
                    ->get()
                : collect(),
            'latestUsers' => User::query()->with('roles')->latest()->limit(5)->get(),
        ]);
    }
}
