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
    /**
     * Photos the gallery of a venue admits.
     */
    public const MAX_PHOTOS = 10;

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
     * Courts the user may see: all with courts.view_all, otherwise the ones they own (partner)
     * or were assigned to as manager.
     */
    public function scopeVisibleTo(Builder $query, User $user): void
    {
        if (! $user->can('courts.view_all')) {
            $query->where(fn (Builder $scoped) => $scoped
                ->where('courts.owner_id', $user->id)
                ->orWhereHas('managers', fn (Builder $managers) => $managers->whereKey($user->id)));
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

    public function managers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'court_managers')->withTimestamps()->orderBy('name');
    }

    public function photos(): HasMany
    {
        return $this->hasMany(CourtPhoto::class)->orderBy('order')->orderBy('id');
    }

    public function matches(): HasMany
    {
        return $this->hasMany(MatchModel::class, 'court_id');
    }

    /**
     * Spaces for gatherings (grill areas, halls...) rented by the hour.
     */
    public function eventSpaces(): HasMany
    {
        return $this->hasMany(EventSpace::class)->chaperone()->orderBy('name');
    }

    /**
     * Sports gear rented with the courts (balls, rackets...).
     */
    public function rentalItems(): HasMany
    {
        return $this->hasMany(RentalItem::class)->orderBy('name');
    }

    /**
     * Shops of the venue (sports gear, drinks...).
     */
    public function stores(): HasMany
    {
        return $this->hasMany(Store::class)->orderBy('name');
    }
}
