<?php

namespace App\Policies;

use App\Models\Court;
use App\Models\User;

/**
 * Ownership rule for courts. Route middleware already checks the action permission
 * (courts.update, court_fields.store, ...); this only decides *which* courts.
 */
class CourtPolicy
{
    /**
     * Users with courts.view_all manage every court; partners the ones they own; managers the ones assigned to them.
     */
    public function manage(User $user, Court $court): bool
    {
        return $user->can('courts.view_all')
            || $court->owner_id === $user->id
            || $court->managers()->whereKey($user->id)->exists();
    }

    /**
     * Only platform staff and the venue owner assign managers (never another manager).
     */
    public function assignManagers(User $user, Court $court): bool
    {
        return $user->can('courts.managers')
            && ($user->can('courts.view_all') || $court->owner_id === $user->id);
    }
}
