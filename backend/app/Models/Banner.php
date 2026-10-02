<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Home carousel slide. Created from the admin panel; the app opens the section given by link_type.
 */
class Banner extends Model
{
    /** Links that need a record (link_id). */
    public const RECORD_LINKS = ['tournament', 'court'];

    /**
     * Destinations the app knows how to open.
     *
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

    /**
     * Active and inside its publication window, in display order.
     */
    public function scopeVisible(Builder $query): void
    {
        $query->where('is_active', true)
            ->where(fn (Builder $q) => $q->whereNull('starts_at')->orWhere('starts_at', '<=', now()))
            ->where(fn (Builder $q) => $q->whereNull('ends_at')->orWhere('ends_at', '>=', now()))
            ->orderBy('sort_order')
            ->orderBy('id');
    }

    public function imageUrl(): ?string
    {
        if (blank($this->image_path)) {
            return null;
        }

        return Str::startsWith($this->image_path, ['http://', 'https://'])
            ? $this->image_path
            : Storage::disk('public')->url($this->image_path);
    }

    /**
     * False when the linked tournament/court no longer exists or is not public, so the app
     * never shows a banner that leads nowhere.
     */
    public function hasValidTarget(): bool
    {
        return match ($this->link_type) {
            'tournament' => $this->link_id !== null
                && Tournament::query()->published()->whereKey($this->link_id)->exists(),
            'court' => $this->link_id !== null && Court::query()->whereKey($this->link_id)->exists(),
            'url' => filled($this->link_url),
            default => array_key_exists($this->link_type, self::LINK_TYPES),
        };
    }
}
