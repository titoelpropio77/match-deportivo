<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class CourtPhoto extends Model
{
    protected $fillable = ['court_id', 'url', 'order'];

    public function court(): BelongsTo
    {
        return $this->belongsTo(Court::class);
    }

    /**
     * Absolute URL: `url` is either external (seeders) or a path on the public disk (uploaded from the admin panel).
     */
    public function getPublicUrlAttribute(): string
    {
        return Str::startsWith($this->url, ['http://', 'https://'])
            ? $this->url
            : Storage::disk('public')->url($this->url);
    }
}
