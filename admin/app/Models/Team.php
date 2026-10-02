<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

/**
 * Team of players, created from the app (schema and rules live in the API backend).
 * The panel only reads teams to show tournament registrations and fixtures.
 */
class Team extends Model
{
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function sport(): BelongsTo
    {
        return $this->belongsTo(Sport::class);
    }

    public function members(): HasMany
    {
        return $this->hasMany(TeamMember::class);
    }

    /**
     * Logos are uploaded by the app to the backend's public disk.
     */
    public function logoUrl(): ?string
    {
        return $this->logo_path ? Storage::disk(CourtPhoto::DISK)->url($this->logo_path) : null;
    }
}
