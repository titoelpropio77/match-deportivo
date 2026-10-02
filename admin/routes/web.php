<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\CourtController;
use App\Http\Controllers\CourtFeatureController;
use App\Http\Controllers\CourtFieldController;
use App\Http\Controllers\CourtManagerController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EventAmenityController;
use App\Http\Controllers\EventReservationController;
use App\Http\Controllers\EventSpaceController;
use App\Http\Controllers\ProductCategoryController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\RentalItemController;
use App\Http\Controllers\ReservationController;
use App\Http\Controllers\Settings\PermissionController;
use App\Http\Controllers\Settings\RoleController;
use App\Http\Controllers\StoreController;
use App\Http\Controllers\StoreOrderController;
use App\Http\Controllers\TournamentController;
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

    Route::post('/courts/{court}/event-spaces', [EventSpaceController::class, 'store'])->middleware('permission:event_spaces.store')->name('courts.event-spaces.store');
    Route::put('/courts/{court}/event-spaces/{eventSpace}', [EventSpaceController::class, 'update'])->middleware('permission:event_spaces.update')->scopeBindings()->name('courts.event-spaces.update');
    Route::delete('/courts/{court}/event-spaces/{eventSpace}', [EventSpaceController::class, 'destroy'])->middleware('permission:event_spaces.destroy')->scopeBindings()->name('courts.event-spaces.destroy');

    Route::post('/courts/{court}/rental-items', [RentalItemController::class, 'store'])->middleware('permission:rental_items.store')->name('courts.rental-items.store');
    Route::put('/courts/{court}/rental-items/{rentalItem}', [RentalItemController::class, 'update'])->middleware('permission:rental_items.update')->scopeBindings()->name('courts.rental-items.update');
    Route::delete('/courts/{court}/rental-items/{rentalItem}', [RentalItemController::class, 'destroy'])->middleware('permission:rental_items.destroy')->scopeBindings()->name('courts.rental-items.destroy');

    Route::post('/courts/{court}/managers', [CourtManagerController::class, 'store'])->middleware('permission:courts.managers')->name('courts.managers.store');
    Route::delete('/courts/{court}/managers/{manager}', [CourtManagerController::class, 'destroy'])->middleware('permission:courts.managers')->name('courts.managers.destroy');

    // Catálogo de características de las canchas físicas
    Route::get('/court-features', [CourtFeatureController::class, 'index'])->middleware('permission:court_features.index')->name('court-features.index');
    Route::get('/court-features/create', [CourtFeatureController::class, 'create'])->middleware('permission:court_features.store')->name('court-features.create');
    Route::post('/court-features', [CourtFeatureController::class, 'store'])->middleware('permission:court_features.store')->name('court-features.store');
    Route::get('/court-features/{courtFeature}/edit', [CourtFeatureController::class, 'edit'])->middleware('permission:court_features.update')->name('court-features.edit');
    Route::put('/court-features/{courtFeature}', [CourtFeatureController::class, 'update'])->middleware('permission:court_features.update')->name('court-features.update');
    Route::delete('/court-features/{courtFeature}', [CourtFeatureController::class, 'destroy'])->middleware('permission:court_features.destroy')->name('court-features.destroy');

    // Catálogo de servicios de los espacios para eventos
    Route::get('/event-amenities', [EventAmenityController::class, 'index'])->middleware('permission:event_amenities.index')->name('event-amenities.index');
    Route::get('/event-amenities/create', [EventAmenityController::class, 'create'])->middleware('permission:event_amenities.store')->name('event-amenities.create');
    Route::post('/event-amenities', [EventAmenityController::class, 'store'])->middleware('permission:event_amenities.store')->name('event-amenities.store');
    Route::get('/event-amenities/{eventAmenity}/edit', [EventAmenityController::class, 'edit'])->middleware('permission:event_amenities.update')->name('event-amenities.edit');
    Route::put('/event-amenities/{eventAmenity}', [EventAmenityController::class, 'update'])->middleware('permission:event_amenities.update')->name('event-amenities.update');
    Route::delete('/event-amenities/{eventAmenity}', [EventAmenityController::class, 'destroy'])->middleware('permission:event_amenities.destroy')->name('event-amenities.destroy');

    // Tiendas de los centros deportivos
    Route::get('/stores', [StoreController::class, 'index'])->middleware('permission:stores.index')->name('stores.index');
    Route::get('/stores/create', [StoreController::class, 'create'])->middleware('permission:stores.store')->name('stores.create');
    Route::post('/stores', [StoreController::class, 'store'])->middleware('permission:stores.store')->name('stores.store');
    Route::get('/stores/{store}', [StoreController::class, 'show'])->middleware('permission:stores.index')->name('stores.show');
    Route::get('/stores/{store}/movements', [StoreController::class, 'movements'])->middleware('permission:stores.index')->name('stores.movements');
    Route::get('/stores/{store}/edit', [StoreController::class, 'edit'])->middleware('permission:stores.update')->name('stores.edit');
    Route::put('/stores/{store}', [StoreController::class, 'update'])->middleware('permission:stores.update')->name('stores.update');
    Route::delete('/stores/{store}', [StoreController::class, 'destroy'])->middleware('permission:stores.destroy')->name('stores.destroy');

    Route::get('/stores/{store}/products/create', [ProductController::class, 'create'])->middleware('permission:products.store')->name('stores.products.create');
    Route::post('/stores/{store}/products', [ProductController::class, 'store'])->middleware('permission:products.store')->name('stores.products.store');
    Route::get('/stores/{store}/products/{product}/edit', [ProductController::class, 'edit'])->middleware('permission:products.update')->scopeBindings()->name('stores.products.edit');
    Route::put('/stores/{store}/products/{product}', [ProductController::class, 'update'])->middleware('permission:products.update')->scopeBindings()->name('stores.products.update');
    Route::delete('/stores/{store}/products/{product}', [ProductController::class, 'destroy'])->middleware('permission:products.destroy')->scopeBindings()->name('stores.products.destroy');
    Route::post('/stores/{store}/products/{product}/stock', [ProductController::class, 'moveStock'])->middleware('permission:products.stock')->scopeBindings()->name('stores.products.stock');

    // Ventas de las tiendas
    Route::get('/store-orders', [StoreOrderController::class, 'index'])->middleware('permission:store_orders.index')->name('store-orders.index');
    Route::get('/store-orders/create', [StoreOrderController::class, 'create'])->middleware('permission:store_orders.store')->name('store-orders.create');
    Route::post('/store-orders', [StoreOrderController::class, 'store'])->middleware('permission:store_orders.store')->name('store-orders.store');
    Route::get('/store-orders/{order}', [StoreOrderController::class, 'show'])->middleware('permission:store_orders.show')->name('store-orders.show');
    Route::post('/store-orders/{order}/deliver', [StoreOrderController::class, 'deliver'])->middleware('permission:store_orders.payments')->name('store-orders.deliver');
    Route::post('/store-orders/{order}/cancel', [StoreOrderController::class, 'cancel'])->middleware('permission:store_orders.cancel')->name('store-orders.cancel');
    Route::post('/store-orders/{order}/refund', [StoreOrderController::class, 'refund'])->middleware('permission:store_orders.payments')->name('store-orders.refund');

    // Catálogo de categorías de productos
    Route::get('/product-categories', [ProductCategoryController::class, 'index'])->middleware('permission:product_categories.index')->name('product-categories.index');
    Route::get('/product-categories/create', [ProductCategoryController::class, 'create'])->middleware('permission:product_categories.store')->name('product-categories.create');
    Route::post('/product-categories', [ProductCategoryController::class, 'store'])->middleware('permission:product_categories.store')->name('product-categories.store');
    Route::get('/product-categories/{productCategory}/edit', [ProductCategoryController::class, 'edit'])->middleware('permission:product_categories.update')->name('product-categories.edit');
    Route::put('/product-categories/{productCategory}', [ProductCategoryController::class, 'update'])->middleware('permission:product_categories.update')->name('product-categories.update');
    Route::delete('/product-categories/{productCategory}', [ProductCategoryController::class, 'destroy'])->middleware('permission:product_categories.destroy')->name('product-categories.destroy');

    // Reservas de canchas
    Route::get('/reservations/agenda', [ReservationController::class, 'agenda'])->middleware('permission:reservations.index')->name('reservations.agenda');
    Route::get('/reservations', [ReservationController::class, 'index'])->middleware('permission:reservations.index')->name('reservations.index');
    Route::get('/reservations/create', [ReservationController::class, 'create'])->middleware('permission:reservations.store')->name('reservations.create');
    Route::post('/reservations', [ReservationController::class, 'store'])->middleware('permission:reservations.store')->name('reservations.store');
    Route::get('/reservations/{reservation}', [ReservationController::class, 'show'])->middleware('permission:reservations.show')->name('reservations.show');
    Route::post('/reservations/{reservation}/cancel', [ReservationController::class, 'cancel'])->middleware('permission:reservations.cancel')->name('reservations.cancel');
    Route::post('/reservations/{reservation}/payment', [ReservationController::class, 'registerPayment'])->middleware('permission:reservations.payments')->name('reservations.payment');
    Route::post('/reservations/{reservation}/refund', [ReservationController::class, 'refund'])->middleware('permission:reservations.payments')->name('reservations.refund');

    // Reservas de espacios para eventos
    Route::get('/event-reservations', [EventReservationController::class, 'index'])->middleware('permission:event_reservations.index')->name('event-reservations.index');
    Route::get('/event-reservations/create', [EventReservationController::class, 'create'])->middleware('permission:event_reservations.store')->name('event-reservations.create');
    Route::post('/event-reservations', [EventReservationController::class, 'store'])->middleware('permission:event_reservations.store')->name('event-reservations.store');
    Route::get('/event-reservations/{reservation}', [EventReservationController::class, 'show'])->middleware('permission:event_reservations.show')->name('event-reservations.show');
    Route::post('/event-reservations/{reservation}/cancel', [EventReservationController::class, 'cancel'])->middleware('permission:event_reservations.cancel')->name('event-reservations.cancel');
    Route::post('/event-reservations/{reservation}/payment', [EventReservationController::class, 'registerPayment'])->middleware('permission:event_reservations.payments')->name('event-reservations.payment');
    Route::post('/event-reservations/{reservation}/refund', [EventReservationController::class, 'refund'])->middleware('permission:event_reservations.payments')->name('event-reservations.refund');

    // Torneos
    Route::get('/tournaments', [TournamentController::class, 'index'])->middleware('permission:tournaments.index')->name('tournaments.index');
    Route::get('/tournaments/create', [TournamentController::class, 'create'])->middleware('permission:tournaments.store')->name('tournaments.create');
    Route::post('/tournaments', [TournamentController::class, 'store'])->middleware('permission:tournaments.store')->name('tournaments.store');
    Route::get('/tournaments/{tournament}', [TournamentController::class, 'show'])->middleware('permission:tournaments.index')->name('tournaments.show');
    Route::get('/tournaments/{tournament}/edit', [TournamentController::class, 'edit'])->middleware('permission:tournaments.update')->name('tournaments.edit');
    Route::put('/tournaments/{tournament}', [TournamentController::class, 'update'])->middleware('permission:tournaments.update')->name('tournaments.update');
    Route::post('/tournaments/{tournament}/status', [TournamentController::class, 'changeStatus'])->middleware('permission:tournaments.update')->name('tournaments.status');
    Route::delete('/tournaments/{tournament}', [TournamentController::class, 'destroy'])->middleware('permission:tournaments.destroy')->name('tournaments.destroy');
    Route::post('/tournaments/{tournament}/registrations/{registration}/cancel', [TournamentController::class, 'cancelRegistration'])->middleware('permission:tournaments.registrations')->scopeBindings()->name('tournaments.registrations.cancel');
    Route::post('/tournaments/{tournament}/registrations/{registration}/payment', [TournamentController::class, 'registerPayment'])->middleware('permission:tournaments.registrations')->scopeBindings()->name('tournaments.registrations.payment');
    Route::post('/tournaments/{tournament}/registrations/{registration}/refund', [TournamentController::class, 'refundRegistration'])->middleware('permission:tournaments.registrations')->scopeBindings()->name('tournaments.registrations.refund');
    Route::post('/tournaments/{tournament}/games', [TournamentController::class, 'storeGame'])->middleware('permission:tournaments.games')->name('tournaments.games.store');
    Route::post('/tournaments/{tournament}/games/generate', [TournamentController::class, 'generateFixture'])->middleware('permission:tournaments.games')->name('tournaments.games.generate');
    Route::put('/tournaments/{tournament}/games/{game}', [TournamentController::class, 'updateGame'])->middleware('permission:tournaments.games')->scopeBindings()->name('tournaments.games.update');
    Route::delete('/tournaments/{tournament}/games/{game}', [TournamentController::class, 'destroyGame'])->middleware('permission:tournaments.games')->scopeBindings()->name('tournaments.games.destroy');

    // Usuarios
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
