<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CityController;
use App\Http\Controllers\Api\CourtBookingController;
use App\Http\Controllers\Api\CourtController;
use App\Http\Controllers\Api\CourtFieldController;
use App\Http\Controllers\Api\EventSpaceController;
use App\Http\Controllers\Api\EventSpaceReservationController;
use App\Http\Controllers\Api\MatchLevelController;
use App\Http\Controllers\Api\PlayerProfileController;
use App\Http\Controllers\Api\RentalItemController;
use App\Http\Controllers\Api\SportController;
use App\Http\Controllers\Api\StoreController;
use App\Http\Controllers\Api\StoreOrderController;
use App\Http\Controllers\Api\TeamController;
use App\Http\Controllers\Api\TournamentController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\MatchController;
use Illuminate\Support\Facades\Route;

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

Route::get('/matches', [MatchController::class, 'index']);
Route::get('/matches/{id}', [MatchController::class, 'show'])->whereNumber('id');
Route::get('/sports', [SportController::class, 'index']);
Route::get('/match-levels', [MatchLevelController::class, 'index']);
Route::get('/cities', [CityController::class, 'index']);
Route::get('/courts', [CourtController::class, 'index']);
Route::get('/courts/{court}/rental-items', [RentalItemController::class, 'index'])->whereNumber('court');
Route::get('/court-fields', [CourtFieldController::class, 'index']);
Route::get('/court-fields/{courtField}/availability', [CourtFieldController::class, 'availability']);
Route::get('/event-spaces', [EventSpaceController::class, 'index']);
Route::get('/event-spaces/{eventSpace}', [EventSpaceController::class, 'show'])->whereNumber('eventSpace');
Route::get('/event-spaces/{eventSpace}/availability', [EventSpaceController::class, 'availability'])->whereNumber('eventSpace');
Route::get('/product-categories', [StoreController::class, 'categories']);
Route::get('/stores', [StoreController::class, 'index']);
Route::get('/stores/{store}', [StoreController::class, 'show'])->whereNumber('store');
Route::get('/stores/{store}/products', [StoreController::class, 'products'])->whereNumber('store');

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/matches/mine', [MatchController::class, 'mine']);
    Route::get('/matches/organized', [MatchController::class, 'organized']);
    Route::get('/matches/organized/past', [MatchController::class, 'organizedPast']);
    Route::post('/matches', [MatchController::class, 'store']);
    Route::post('/matches/{id}/join', [MatchController::class, 'join'])
        ->whereNumber('id');
    Route::delete('/matches/{id}/leave', [MatchController::class, 'leave'])
        ->whereNumber('id');
    Route::delete('/matches/{id}', [MatchController::class, 'destroy'])
        ->whereNumber('id');
    Route::get('/matches/{id}/rating-tags', [MatchController::class, 'ratingTags'])
        ->whereNumber('id');
    Route::post('/matches/{id}/finish', [MatchController::class, 'finish'])
        ->whereNumber('id');
    Route::post('/matches/{id}/players', [MatchController::class, 'addPlayer'])
        ->whereNumber('id');
    Route::post('/matches/{id}/players/{playerId}/review', [MatchController::class, 'reviewPlayer'])
        ->whereNumber('id')
        ->whereNumber('playerId');
    Route::delete('/matches/{id}/players/{playerId}', [MatchController::class, 'removePlayer'])
        ->whereNumber('id')
        ->whereNumber('playerId');
    Route::get('/users/search', [UserController::class, 'search']);
    Route::get('/users/{user}/profile', [PlayerProfileController::class, 'show'])->whereNumber('user');
    Route::patch('/me', [PlayerProfileController::class, 'update']);

    Route::get('/tournaments', [TournamentController::class, 'index']);
    Route::get('/tournaments/mine', [TournamentController::class, 'mine']);
    Route::get('/tournaments/{tournament}', [TournamentController::class, 'show'])->whereNumber('tournament');
    Route::post('/tournaments/{tournament}/registrations', [TournamentController::class, 'register'])->whereNumber('tournament');
    Route::post('/tournament-registrations/{registration}/pay', [TournamentController::class, 'pay'])->whereNumber('registration');
    Route::post('/tournament-registrations/{registration}/cancel', [TournamentController::class, 'cancel'])->whereNumber('registration');

    Route::get('/teams', [TeamController::class, 'index']);
    Route::get('/teams/search', [TeamController::class, 'search']);
    Route::post('/teams', [TeamController::class, 'store']);
    Route::get('/teams/{team}', [TeamController::class, 'show'])->whereNumber('team');
    // POST because it is multipart (logo upload).
    Route::post('/teams/{team}', [TeamController::class, 'update'])->whereNumber('team');
    Route::delete('/teams/{team}', [TeamController::class, 'destroy'])->whereNumber('team');
    Route::post('/teams/{team}/members', [TeamController::class, 'addMember'])->whereNumber('team');
    Route::patch('/teams/{team}/members/{user}', [TeamController::class, 'updateMember'])->whereNumber(['team', 'user']);
    Route::delete('/teams/{team}/members/{user}', [TeamController::class, 'removeMember'])->whereNumber(['team', 'user']);
    Route::post('/court-bookings', [CourtBookingController::class, 'store']);
    Route::post('/court-bookings/{code}/pay', [CourtBookingController::class, 'pay']);
    Route::post('/court-bookings/{code}/cancel', [CourtBookingController::class, 'cancel']);
    Route::get('/court-reservations', [CourtFieldController::class, 'mine']);
    Route::post('/court-reservations', [CourtFieldController::class, 'store']);
    Route::post('/court-reservations/{reservation}/pay', [CourtFieldController::class, 'pay']);
    Route::post('/court-reservations/{reservation}/cancel', [CourtFieldController::class, 'cancel']);

    Route::get('/event-space-reservations', [EventSpaceReservationController::class, 'mine']);
    Route::post('/event-space-reservations', [EventSpaceReservationController::class, 'store']);
    Route::post('/event-space-reservations/{reservation}/pay', [EventSpaceReservationController::class, 'pay'])->whereNumber('reservation');
    Route::post('/event-space-reservations/{reservation}/cancel', [EventSpaceReservationController::class, 'cancel'])->whereNumber('reservation');

    Route::get('/store-orders', [StoreOrderController::class, 'mine']);
    Route::post('/store-orders', [StoreOrderController::class, 'store']);
    Route::post('/store-orders/{order}/pay', [StoreOrderController::class, 'pay'])->whereNumber('order');
    Route::post('/store-orders/{order}/cancel', [StoreOrderController::class, 'cancel'])->whereNumber('order');
});
