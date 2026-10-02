<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolesAndPermissionsSeeder extends Seeder
{
    /**
     * Every admin screen/action is guarded by one of these. Naming: <module>.<action>.
     *
     * @var list<string>
     */
    public const PERMISSIONS = [
        'dashboard.index',

        'courts.index',
        'courts.show',
        'courts.store',
        'courts.update',
        'courts.destroy',
        // Without it a user only sees/manages the courts they own (courts.owner_id) or manage (court_managers).
        'courts.view_all',
        // Assign/remove managers of a court (owners: only on their own courts).
        'courts.managers',

        'court_fields.store',
        'court_fields.update',
        'court_fields.destroy',

        // Catalog of amenities physical courts can have (shared by every venue).
        'court_features.index',
        'court_features.store',
        'court_features.update',
        'court_features.destroy',

        // Sports gear rented with the courts (balls, rackets...), managed from the venue edit screen.
        'rental_items.store',
        'rental_items.update',
        'rental_items.destroy',

        // Event spaces (grill areas, halls...) of a venue, managed from its edit screen.
        'event_spaces.store',
        'event_spaces.update',
        'event_spaces.destroy',

        // Catalog of what event spaces can include (shared by every venue).
        'event_amenities.index',
        'event_amenities.store',
        'event_amenities.update',
        'event_amenities.destroy',

        // Reservations of event spaces, same actions as court reservations.
        'event_reservations.index',
        'event_reservations.show',
        'event_reservations.store',
        'event_reservations.cancel',
        'event_reservations.payments',

        // Stores of a venue: partners/admins open them, managers run them.
        'stores.index',
        'stores.store',
        'stores.update',
        'stores.destroy',

        // Products of a store and their stock (restock, loss, physical count).
        'products.store',
        'products.update',
        'products.destroy',
        'products.stock',

        // Catalog of product categories (shared by every store).
        'product_categories.index',
        'product_categories.store',
        'product_categories.update',
        'product_categories.destroy',

        // Store sales: list, counter sales, cancellations, deliveries and refunds.
        'store_orders.index',
        'store_orders.show',
        'store_orders.store',
        'store_orders.cancel',
        'store_orders.payments',

        // Reservations of the venues the user can see (all of them with courts.view_all).
        'reservations.index',
        'reservations.show',
        // Register walk-in / phone bookings from the panel.
        'reservations.store',
        // Cancel a booking (with a reason shown to the player).
        'reservations.cancel',
        // Register payments collected at the venue and refunds.
        'reservations.payments',

        // Tournaments of the venues the user can see.
        'tournaments.index',
        'tournaments.store',
        'tournaments.update',
        'tournaments.destroy',
        // Team registrations: cancel, register payments and refunds.
        'tournaments.registrations',
        // Fixture and results.
        'tournaments.games',

        // Home carousel of the app (platform-wide, not tied to a venue).
        'banners.index',
        'banners.store',
        'banners.update',
        'banners.destroy',

        'users.index',
        'users.show',
        'users.store',
        'users.update',
        'users.destroy',

        'roles.index',
        'roles.store',
        'roles.update',
        'roles.destroy',

        'permissions.index',
        'permissions.store',
        'permissions.update',
        'permissions.destroy',
        'permissions.assign',
    ];

    /**
     * Default permissions per role. superadmin is not listed: it passes every check via Gate::before.
     *
     * @var array<string, list<string>>
     */
    private const ROLE_DEFAULTS = [
        // Platform staff: every court; creates users and assigns them roles (never superadmin).
        'admin' => [
            'dashboard.index',
            'courts.index',
            'courts.show',
            'courts.store',
            'courts.update',
            'courts.destroy',
            'courts.view_all',
            'courts.managers',
            'court_fields.store',
            'court_fields.update',
            'court_fields.destroy',
            'court_features.index',
            'court_features.store',
            'court_features.update',
            'court_features.destroy',
            'event_spaces.store',
            'event_spaces.update',
            'event_spaces.destroy',
            'event_amenities.index',
            'event_amenities.store',
            'event_amenities.update',
            'event_amenities.destroy',
            'stores.index',
            'stores.store',
            'stores.update',
            'stores.destroy',
            'products.store',
            'products.update',
            'products.destroy',
            'products.stock',
            'product_categories.index',
            'product_categories.store',
            'product_categories.update',
            'product_categories.destroy',
            'store_orders.index',
            'store_orders.show',
            'store_orders.store',
            'store_orders.cancel',
            'store_orders.payments',
            'rental_items.store',
            'rental_items.update',
            'rental_items.destroy',
            'event_reservations.index',
            'event_reservations.show',
            'event_reservations.store',
            'event_reservations.cancel',
            'event_reservations.payments',
            'reservations.index',
            'reservations.show',
            'reservations.store',
            'reservations.cancel',
            'reservations.payments',
            'tournaments.index',
            'tournaments.store',
            'tournaments.update',
            'tournaments.destroy',
            'tournaments.registrations',
            'tournaments.games',
            'banners.index',
            'banners.store',
            'banners.update',
            'banners.destroy',
            'users.index',
            'users.show',
            'users.store',
            'users.update',
        ],
        // Court owner: only the courts assigned to them; can add managers to them.
        'partner' => [
            'dashboard.index',
            'courts.index',
            'courts.show',
            'courts.update',
            'courts.managers',
            'court_fields.store',
            'court_fields.update',
            'court_fields.destroy',
            'event_spaces.store',
            'event_spaces.update',
            'event_spaces.destroy',
            'rental_items.store',
            'rental_items.update',
            'rental_items.destroy',
            'stores.index',
            'stores.store',
            'stores.update',
            'stores.destroy',
            'products.store',
            'products.update',
            'products.destroy',
            'products.stock',
            'store_orders.index',
            'store_orders.show',
            'store_orders.store',
            'store_orders.cancel',
            'store_orders.payments',
            'event_reservations.index',
            'event_reservations.show',
            'event_reservations.store',
            'event_reservations.cancel',
            'event_reservations.payments',
            'reservations.index',
            'reservations.show',
            'reservations.store',
            'reservations.cancel',
            'reservations.payments',
            'tournaments.index',
            'tournaments.store',
            'tournaments.update',
            'tournaments.destroy',
            'tournaments.registrations',
            'tournaments.games',
        ],
        // Staff assigned by an owner: only the courts they manage, cannot delete or assign managers.
        'manager' => [
            'dashboard.index',
            'courts.index',
            'courts.show',
            'courts.update',
            'court_fields.store',
            'court_fields.update',
            'event_spaces.store',
            'event_spaces.update',
            'rental_items.store',
            'rental_items.update',
            'stores.index',
            'stores.update',
            'products.store',
            'products.update',
            'products.stock',
            'store_orders.index',
            'store_orders.show',
            'store_orders.store',
            'store_orders.cancel',
            'store_orders.payments',
            'event_reservations.index',
            'event_reservations.show',
            'event_reservations.store',
            'event_reservations.cancel',
            'event_reservations.payments',
            'reservations.index',
            'reservations.show',
            'reservations.store',
            'reservations.cancel',
            'reservations.payments',
            'tournaments.index',
            'tournaments.store',
            'tournaments.update',
            'tournaments.registrations',
            'tournaments.games',
        ],
        'cliente' => [],
    ];

    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $newPermissions = [];
        foreach (self::PERMISSIONS as $name) {
            if (Permission::findOrCreate($name, 'web')->wasRecentlyCreated) {
                $newPermissions[] = $name;
            }
        }

        Role::findOrCreate('superadmin', 'web');

        // Defaults go to new roles in full, and to existing roles only for permissions that did not
        // exist before. Changes made from the panel survive re-seeding (Docker seeds on every start).
        foreach (self::ROLE_DEFAULTS as $roleName => $defaults) {
            $role = Role::findOrCreate($roleName, 'web');
            $grant = $role->wasRecentlyCreated ? $defaults : array_values(array_intersect($defaults, $newPermissions));
            if ($grant !== []) {
                $role->givePermissionTo($grant);
            }
        }

        $superadmin = User::query()->firstOrCreate(
            ['email' => env('ADMIN_EMAIL', 'admin@matchdeportivo.test')],
            [
                'name' => 'Super Admin',
                'password' => Hash::make(env('ADMIN_PASSWORD', 'password')),
            ],
        );
        $superadmin->assignRole('superadmin');
    }
}
