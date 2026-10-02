<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Shop of a sports center. Schema is owned by the API backend migrations.
 */
class Store extends Model
{
    protected $fillable = [
        'court_id',
        'name',
        'description',
        'phone',
        'cover_path',
        'is_active',
        'created_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function court(): BelongsTo
    {
        return $this->belongsTo(Court::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(ProductCategory::class)->orderBy('name');
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(StoreOrder::class);
    }

    /**
     * Stores of the venues the user can see (partner: owned, manager: assigned).
     */
    public function scopeVisibleTo(Builder $query, User $user): void
    {
        if (! $user->can('courts.view_all')) {
            $query->whereHas('court', fn (Builder $court) => $court->visibleTo($user));
        }
    }

    public function isUploadedCover(): bool
    {
        return filled($this->cover_path) && ! Str::startsWith($this->cover_path, ['http://', 'https://']);
    }

    public function coverUrl(): ?string
    {
        if (blank($this->cover_path)) {
            return null;
        }

        return $this->isUploadedCover() ? Storage::disk(CourtPhoto::DISK)->url($this->cover_path) : $this->cover_path;
    }

    public function deleteCover(): void
    {
        if ($this->isUploadedCover()) {
            Storage::disk(CourtPhoto::DISK)->delete($this->cover_path);
        }
    }
}
