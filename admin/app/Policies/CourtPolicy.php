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
     * Users with courts.view_all manage every court; the rest (partners) only the ones they own.
     */
    public function manage(User $user, Court $court): bool
    {
        return $user->can('courts.view_all') || $court->owner_id === $user->id;
    }
}
