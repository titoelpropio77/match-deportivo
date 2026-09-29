<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CityController;
use App\Http\Controllers\Api\CourtController;
use App\Http\Controllers\Api\CourtFieldController;
use App\Http\Controllers\Api\MatchLevelController;
use App\Http\Controllers\Api\SportController;
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
Route::get('/court-fields', [CourtFieldController::class, 'index']);
Route::get('/court-fields/{courtField}/availability', [CourtFieldController::class, 'availability']);

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
    Route::post('/court-reservations', [CourtFieldController::class, 'store']);
});