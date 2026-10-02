<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Product gallery photo: an external URL (seeders) or a path on the backend's public disk.
 */
class ProductPhoto extends Model
{
    protected $fillable = ['product_id', 'path', 'order'];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function isUploaded(): bool
    {
        return ! Str::startsWith($this->path, ['http://', 'https://']);
    }

    protected function src(): Attribute
    {
        return Attribute::get(fn (): string => $this->isUploaded()
            ? Storage::disk(CourtPhoto::DISK)->url($this->path)
            : $this->path);
    }

    public function deleteFile(): void
    {
        if ($this->isUploaded()) {
            Storage::disk(CourtPhoto::DISK)->delete($this->path);
        }
    }
}
