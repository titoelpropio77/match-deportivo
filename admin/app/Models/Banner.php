<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Home carousel slide of the app (table owned by the backend migrations).
 * Keep LINK_TYPES in sync with backend App\Models\Banner and the app's BannerLinkType.
 */
class Banner extends Model
{
    /** Links that point to a record (link_id). */
    public const RECORD_LINKS = ['tournament', 'court'];

    /**
     * @var array<string, string>
     */
    public const LINK_TYPES = [
        'none' => 'Sin enlace',
        'reserve_courts' => 'Reservar cancha',
        'court' => 'Un centro deportivo',
        'tournaments' => 'Lista de torneos',
        'tournament' => 'Un torneo',
        'teams' => 'Equipos',
        'stores' => 'Tiendas',
        'event_spaces' => 'Espacios para eventos',
        'url' => 'Enlace externo (URL)',
    ];

    protected $fillable = [
        'title',
        'subtitle',
        'button_label',
        'image_path',
        'background_color',
        'link_type',
        'link_id',
        'link_url',
        'sort_order',
        'is_active',
        'starts_at',
        'ends_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'link_id' => 'integer',
            'sort_order' => 'integer',
            'is_active' => 'boolean',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
        ];
    }

    public function isUploadedImage(): bool
    {
        return filled($this->image_path) && ! Str::startsWith($this->image_path, ['http://', 'https://']);
    }

    public function imageUrl(): ?string
    {
        if (blank($this->image_path)) {
            return null;
        }

        return $this->isUploadedImage() ? Storage::disk(CourtPhoto::DISK)->url($this->image_path) : $this->image_path;
    }

    public function deleteImage(): void
    {
        if ($this->isUploadedImage()) {
            Storage::disk(CourtPhoto::DISK)->delete($this->image_path);
        }
    }

    /**
     * "Un torneo: Copa Wally Sur 2026", "Enlace externo: https://...".
     */
    public function linkDescription(): string
    {
        $label = self::LINK_TYPES[$this->link_type] ?? $this->link_type;

        $target = match ($this->link_type) {
            'tournament' => Tournament::query()->whereKey($this->link_id)->value('name') ?? '(eliminado)',
            'court' => Court::query()->whereKey($this->link_id)->value('name') ?? '(eliminado)',
            'url' => $this->link_url,
            default => null,
        };

        return $target === null ? $label : "{$label}: {$target}";
    }

    /**
     * Shown in the app right now (active and inside its publication window).
     */
    public function isLive(): bool
    {
        return $this->is_active
            && ($this->starts_at === null || $this->starts_at->lte(now()))
            && ($this->ends_at === null || $this->ends_at->gte(now()));
    }
}
