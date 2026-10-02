<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

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
        'review_data',
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
     * Get the user who created this court.
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    /**
     * City where the venue is located, used to segment courts.
     */
    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class);
    }

    /**
     * Get the sports (categories) offered at this court.
     */
    public function sports(): BelongsToMany
    {
        return $this->belongsToMany(Sport::class, 'court_sport');
    }

    /**
     * Courts whose name, address or city contains the term, ignoring case and (on PostgreSQL) accents.
     */
    public function scopeSearch(Builder $query, string $term): void
    {
        $normalize = fn (string $column): string => $query->getConnection()->getDriverName() === 'pgsql'
            ? "translate(lower({$column}), 'áàäéèëíìïóòöúùüñ', 'aaaeeeiiiooouuun')"
            : "lower({$column})";
        $like = '%'.Str::lower(Str::ascii($term)).'%';

        $query->where(fn (Builder $matches) => $matches
            ->whereRaw($normalize('courts.name').' like ?', [$like])
            ->orWhereRaw($normalize('courts.address').' like ?', [$like])
            ->orWhereHas('city', fn (Builder $city) => $city->whereRaw($normalize('cities.name').' like ?', [$like])));
    }

    /**
     * Get the photo gallery for this court.
     */
    public function photos(): HasMany
    {
        return $this->hasMany(CourtPhoto::class)->orderBy('order');
    }

    /**
     * Get the reviews left by players for this court.
     */
    public function reviews(): HasMany
    {
        return $this->hasMany(CourtReview::class);
    }

    /**
     * Get the matches scheduled at this court.
     */
    public function matches(): HasMany
    {
        return $this->hasMany(MatchModel::class, 'court_id');
    }

    /**
     * Physical courts inside this venue. Booking one blocks that court only.
     */
    public function fields(): HasMany
    {
        return $this->hasMany(CourtField::class);
    }

    /**
     * Spaces for gatherings (grill areas, halls...) rented by the hour.
     */
    public function eventSpaces(): HasMany
    {
        return $this->hasMany(EventSpace::class);
    }

    /**
     * Sports gear rented with the courts (balls, rackets...).
     */
    public function rentalItems(): HasMany
    {
        return $this->hasMany(RentalItem::class);
    }
}
