<?php

namespace App\Models;

use App\Enums\MatchGender;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class Team extends Model
{
    public const MAX_MEMBERS = 30;

    protected $fillable = [
        'owner_id',
        'sport_id',
        'level_id',
        'name',
        'short_name',
        'gender',
        'primary_color',
        'description',
        'logo_path',
    ];

    protected $hidden = ['logo_path', 'pivot'];

    protected $appends = ['logo_url'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'gender' => MatchGender::class,
        ];
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function sport(): BelongsTo
    {
        return $this->belongsTo(Sport::class);
    }

    public function level(): BelongsTo
    {
        return $this->belongsTo(MatchLevel::class, 'level_id');
    }

    public function members(): HasMany
    {
        return $this->hasMany(TeamMember::class);
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'team_members')->withTimestamps();
    }

    public function matches(): BelongsToMany
    {
        return $this->belongsToMany(MatchModel::class, 'match_team', 'team_id', 'match_id');
    }

    /**
     * Teams the user plays in (as captain or player).
     */
    public function scopeWithMember(Builder $query, int $userId): void
    {
        $query->whereHas('members', fn (Builder $members) => $members->where('user_id', $userId));
    }

    /**
     * Teams whose name or abbreviation contains the term, ignoring case and (on PostgreSQL) accents.
     */
    public function scopeSearch(Builder $query, string $term): void
    {
        $normalize = fn (string $column): string => $query->getConnection()->getDriverName() === 'pgsql'
            ? "translate(lower({$column}), 'áàäéèëíìïóòöúùüñ', 'aaaeeeiiiooouuun')"
            : "lower({$column})";
        $like = '%'.Str::lower(Str::ascii($term)).'%';

        $query->where(fn (Builder $matches) => $matches
            ->whereRaw($normalize('teams.name').' like ?', [$like])
            ->orWhereRaw($normalize("coalesce(teams.short_name, '')").' like ?', [$like]));
    }

    public function getLogoUrlAttribute(): ?string
    {
        return $this->logo_path ? Storage::disk('public')->url($this->logo_path) : null;
    }

    public function deleteLogo(): void
    {
        if ($this->logo_path) {
            Storage::disk('public')->delete($this->logo_path);
        }
    }
}
