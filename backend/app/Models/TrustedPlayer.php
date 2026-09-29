<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TrustedPlayer extends Model
{
    protected $fillable = [
        'organizer_id',
        'player_id',
    ];

    /**
     * Get the organizer who trusted this player.
     */
    public function organizer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'organizer_id');
    }

    /**
     * Get the trusted player.
     */
    public function player(): BelongsTo
    {
        return $this->belongsTo(User::class, 'player_id');
    }
}
