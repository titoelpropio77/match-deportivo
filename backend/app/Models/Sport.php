<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Sport extends Model
{
    protected $fillable = ['key', 'name'];

    public function matches(): HasMany
    {
        return $this->hasMany(MatchModel::class, 'sport_id');
    }

    /**
     * Get the courts that offer this sport.
     */
    public function courts(): BelongsToMany
    {
        return $this->belongsToMany(Court::class, 'court_sport');
    }

    public function fields(): BelongsToMany
    {
        return $this->belongsToMany(CourtField::class, 'court_field_sport');
    }

    /**
     * Get the rating tags available for this sport.
     */
    public function ratingTags(): HasMany
    {
        return $this->hasMany(RatingTag::class)->orderBy('sort_order')->orderBy('id');
    }
}
