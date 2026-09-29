<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class PlayerRating extends Model
{
    protected $fillable = [
        'match_id',
        'reviewer_id',
        'user_id',
        'stars',
        'did_not_attend',
    ];

    /**
     * Get the match this rating belongs to.
     */
    public function match(): BelongsTo
    {
        return $this->belongsTo(MatchModel::class, 'match_id');
    }

    /**
     * Get the organizer who submitted the rating.
     */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewer_id');
    }

    /**
     * Get the player who was rated.
     */
    public function player(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Get the tags selected for this rating.
     */
    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(RatingTag::class, 'player_rating_tag');
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'stars' => 'integer',
            'did_not_attend' => 'boolean',
        ];
    }
}
