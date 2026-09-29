<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Sports venue (complejo). Schema is owned by the API backend migrations.
 */
class Court extends Model
{
    protected $fillable = [
        'owner_id',
        'city_id',
        'name',
        'address',
        'latitude',
        'longitude',
        'opening_time',
        'closing_time',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'review_data' => 'array',
        ];
    }

    /**
     * Courts the user may see: all with courts.view_all, otherwise only the ones they own (partner).
     */
    public function scopeVisibleTo(Builder $query, User $user): void
    {
        if (! $user->can('courts.view_all')) {
            $query->where('courts.owner_id', $user->id);
        }
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class);
    }

    public function sports(): BelongsToMany
    {
        return $this->belongsToMany(Sport::class, 'court_sport');
    }

    public function fields(): HasMany
    {
        return $this->hasMany(CourtField::class)->orderBy('name');
    }

    public function matches(): HasMany
    {
        return $this->hasMany(MatchModel::class, 'court_id');
    }
}
