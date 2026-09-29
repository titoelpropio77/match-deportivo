<?php

namespace App\Models;

use App\Enums\MatchPlayerStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MatchPlayer extends Model
{
    protected $fillable = [
        'match_id',
        'user_id',
        'quantity_slots',
        'status',
    ];

    /**
     * Get the match this registration belongs to.
     */
    public function match(): BelongsTo
    {
        return $this->belongsTo(MatchModel::class, 'match_id');
    }

    /**
     * Get the user who made this registration.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'quantity_slots' => 'integer',
            'status' => MatchPlayerStatus::class,
        ];
    }
}