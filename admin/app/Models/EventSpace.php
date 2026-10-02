<?php

namespace App\Models;

use App\Enums\EventSpaceType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Space of a sports center rented by the hour for gatherings. Schema owned by the backend
 * (App\Models\EventSpace there).
 */
class EventSpace extends Model
{
    protected $fillable = [
        'court_id',
        'name',
        'type',
        'description',
        'price_per_hour',
        'capacity',
        'min_hours',
        'rules',
        'photo_path',
        'opening_time',
        'closing_time',
        'is_active',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => EventSpaceType::class,
            'price_per_hour' => 'decimal:2',
            'capacity' => 'integer',
            'min_hours' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function court(): BelongsTo
    {
        return $this->belongsTo(Court::class);
    }

    public function amenities(): BelongsToMany
    {
        return $this->belongsToMany(EventAmenity::class, 'event_space_amenity')->orderBy('name');
    }

    public function reservations(): HasMany
    {
        return $this->hasMany(EventSpaceReservation::class);
    }

    public function isPhotoUploaded(): bool
    {
        return self::isUploadedPath($this->photo_path);
    }

    private static function isUploadedPath(?string $path): bool
    {
        return filled($path) && ! Str::startsWith($path, ['http://', 'https://']);
    }

    public function photoUrl(): ?string
    {
        if (blank($this->photo_path)) {
            return null;
        }

        return $this->isPhotoUploaded()
            ? Storage::disk(CourtPhoto::DISK)->url($this->photo_path)
            : $this->photo_path;
    }

    public function deletePhoto(): void
    {
        self::deleteStoredPhoto($this->photo_path);
    }

    /**
     * Removes an uploaded photo file (external URLs are left alone).
     */
    public static function deleteStoredPhoto(?string $path): void
    {
        if (self::isUploadedPath($path)) {
            Storage::disk(CourtPhoto::DISK)->delete($path);
        }
    }

    /**
     * Own schedule, or the sports center's when the space has none.
     */
    public function openingTime(): string
    {
        return substr($this->opening_time ?? $this->court->opening_time, 0, 5);
    }

    public function closingTime(): string
    {
        return substr($this->closing_time ?? $this->court->closing_time, 0, 5);
    }
}
