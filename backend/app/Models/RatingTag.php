<?php

namespace App\Models;

use App\Enums\RatingPolarity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class RatingTag extends Model
{
    protected $fillable = [
        'sport_id',
        'key',
        'label',
        'polarity',
        'marks_absence',
        'sort_order',
    ];

    /**
     * Get the sport this tag belongs to.
     */
    public function sport(): BelongsTo
    {
        return $this->belongsTo(Sport::class);
    }

    /**
     * Get the player ratings that selected this tag.
     */
    public function ratings(): BelongsToMany
    {
        return $this->belongsToMany(PlayerRating::class, 'player_rating_tag');
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'polarity' => RatingPolarity::class,
            'marks_absence' => 'boolean',
            'sort_order' => 'integer',
        ];
    }
}
