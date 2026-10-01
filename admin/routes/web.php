<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\CourtController;
use App\Http\Controllers\CourtFieldController;
use App\Http\Controllers\CourtManagerController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Settings\PermissionController;
use App\Http\Controllers\Settings\RoleController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'create'])->name('login');
    Route::post('/login', [LoginController::class, 'store'])
        ->middleware('throttle:5,1')
        ->name('login.store');
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [LoginController::class, 'destroy'])->name('logout');

    Route::redirect('/', '/dashboard');
    Route::get('/dashboard', DashboardController::class)
        ->middleware('permission:dashboard.index')
        ->name('dashboard');

    // Centros deportivos
    Route::get('/courts/data', [CourtController::class, 'data'])->middleware('permission:courts.index')->name('courts.data');
    Route::get('/courts', [CourtController::class, 'index'])->middleware('permission:courts.index')->name('courts.index');
    Route::get('/courts/create', [CourtController::class, 'create'])->middleware('permission:courts.store')->name('courts.create');
    Route::post('/courts', [CourtController::class, 'store'])->middleware('permission:courts.store')->name('courts.store');
    Route::get('/courts/{court}', [CourtController::class, 'show'])->middleware('permission:courts.show')->name('courts.show');
    Route::get('/courts/{court}/edit', [CourtController::class, 'edit'])->middleware('permission:courts.update')->name('courts.edit');
    Route::put('/courts/{court}', [CourtController::class, 'update'])->middleware('permission:courts.update')->name('courts.update');
    Route::delete('/courts/{court}', [CourtController::class, 'destroy'])->middleware('permission:courts.destroy')->name('courts.destroy');

    Route::post('/courts/{court}/fields', [CourtFieldController::class, 'store'])->middleware('permission:court_fields.store')->name('courts.fields.store');
    Route::put('/courts/{court}/fields/{field}', [CourtFieldController::class, 'update'])->middleware('permission:court_fields.update')->scopeBindings()->name('courts.fields.update');
    Route::delete('/courts/{court}/fields/{field}', [CourtFieldController::class, 'destroy'])->middleware('permission:court_fields.destroy')->scopeBindings()->name('courts.fields.destroy');

    Route::post('/courts/{court}/managers', [CourtManagerController::class, 'store'])->middleware('permission:courts.managers')->name('courts.managers.store');
    Route::delete('/courts/{court}/managers/{manager}', [CourtManagerController::class, 'destroy'])->middleware('permission:courts.managers')->name('courts.managers.destroy');

    // Usuarios
    Route::get('/users/data', [UserController::class, 'data'])->middleware('permission:users.index')->name('users.data');
    Route::get('/users', [UserController::class, 'index'])->middleware('permission:users.index')->name('users.index');
    Route::get('/users/create', [UserController::class, 'create'])->middleware('permission:users.store')->name('users.create');
    Route::post('/users', [UserController::class, 'store'])->middleware('permission:users.store')->name('users.store');
    Route::get('/users/{user}', [UserController::class, 'show'])->middleware('permission:users.show')->name('users.show');
    Route::get('/users/{user}/edit', [UserController::class, 'edit'])->middleware('permission:users.update')->name('users.edit');
    Route::put('/users/{user}', [UserController::class, 'update'])->middleware('permission:users.update')->name('users.update');
    Route::delete('/users/{user}', [UserController::class, 'destroy'])->middleware('permission:users.destroy')->name('users.destroy');

    // Configuración: roles y permisos
    Route::prefix('settings')->name('settings.')->group(function () {
        Route::get('/permissions', [PermissionController::class, 'index'])->middleware('permission:permissions.index')->name('permissions.index');
        Route::get('/permissions/create', [PermissionController::class, 'create'])->middleware('permission:permissions.store')->name('permissions.create');
        Route::post('/permissions', [PermissionController::class, 'store'])->middleware('permission:permissions.store')->name('permissions.store');
        Route::get('/permissions/{permission}/edit', [PermissionController::class, 'edit'])->middleware('permission:permissions.update')->name('permissions.edit');
        Route::put('/permissions/{permission}', [PermissionController::class, 'update'])->middleware('permission:permissions.update')->name('permissions.update');
        Route::delete('/permissions/{permission}', [PermissionController::class, 'destroy'])->middleware('permission:permissions.destroy')->name('permissions.destroy');
        Route::post('/permissions/{permission}/roles/{role}', [PermissionController::class, 'toggle'])->middleware('permission:permissions.assign')->name('permissions.toggle');

        Route::get('/roles', [RoleController::class, 'index'])->middleware('permission:roles.index')->name('roles.index');
        Route::get('/roles/create', [RoleController::class, 'create'])->middleware('permission:roles.store')->name('roles.create');
        Route::post('/roles', [RoleController::class, 'store'])->middleware('permission:roles.store')->name('roles.store');
        Route::get('/roles/{role}/edit', [RoleController::class, 'edit'])->middleware('permission:roles.update')->name('roles.edit');
        Route::put('/roles/{role}', [RoleController::class, 'update'])->middleware('permission:roles.update')->name('roles.update');
        Route::delete('/roles/{role}', [RoleController::class, 'destroy'])->middleware('permission:roles.destroy')->name('roles.destroy');
    });
});
