<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Venue gallery photo. `url` is either an external URL (seeders) or a path
 * on the backend's public disk (uploaded from this panel).
 */
class CourtPhoto extends Model
{
    public const DISK = 'backend_public';

    protected $fillable = ['court_id', 'url', 'order'];

    public function court(): BelongsTo
    {
        return $this->belongsTo(Court::class);
    }

    public function isUploaded(): bool
    {
        return ! Str::startsWith($this->url, ['http://', 'https://']);
    }

    protected function src(): Attribute
    {
        return Attribute::get(fn (): string => $this->isUploaded()
            ? Storage::disk(self::DISK)->url($this->url)
            : $this->url);
    }

    /**
     * Removes the stored file (external URLs are left alone).
     */
    public function deleteFile(): void
    {
        if ($this->isUploaded()) {
            Storage::disk(self::DISK)->delete($this->url);
        }
    }
}
