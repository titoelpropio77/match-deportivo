<?php

namespace App\Models;

use App\Enums\MatchGender;
use App\Enums\MatchPlayerStatus;
use App\Enums\MatchStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

class MatchModel extends Model
{
    use SoftDeletes;

    protected $table = 'matches';

    protected $fillable = [
        'organizer_id',
        'sport_id',
        'level_id',
        'court_id',
        'gender',
        'payment_qr_path',
        'scheduled_at',
        'start_time',
        'end_time',
        'total_players',
        'missing_players',
        'max_players',
        'status',
    ];

    protected $hidden = [
        'payment_qr_path',
    ];

    protected $appends = [
        'payment_qr_url',
    ];

    /**
     * Get the user who organized the match.
     */
    public function organizer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'organizer_id');
    }

    /**
     * Get the player registrations for the match.
     */
    public function players(): HasMany
    {
        return $this->hasMany(MatchPlayer::class, 'match_id')
            ->orderBy('created_at')
            ->orderBy('id');
    }

    /**
     * Get the sport played in the match.
     */
    public function sport(): BelongsTo
    {
        return $this->belongsTo(Sport::class);
    }

    /**
     * Get the required skill level of the match.
     */
    public function level(): BelongsTo
    {
        return $this->belongsTo(MatchLevel::class, 'level_id');
    }

    /**
     * Get the court where the match takes place.
     */
    public function court(): BelongsTo
    {
        return $this->belongsTo(Court::class);
    }

    /**
     * Physical courts of the venue booked for this match (e.g. "Cancha 1", "Cancha 2").
     */
    public function courtFields(): BelongsToMany
    {
        return $this->belongsToMany(CourtField::class, 'match_court_field', 'match_id', 'court_field_id')
            ->orderBy('court_fields.name');
    }

    /**
     * Get the organizer ratings submitted when the match was finished.
     */
    public function ratings(): HasMany
    {
        return $this->hasMany(PlayerRating::class, 'match_id');
    }

    /**
     * Public URL of the court-payment QR, visible only to the organizer
     * and to players already registered on the match.
     */
    public function getPaymentQrUrlAttribute(): ?string
    {
        if (blank($this->payment_qr_path)) {
            return null;
        }

        $viewerId = auth('sanctum')->id();
        if ($viewerId === null || ! $this->viewerMaySeePaymentQr((int) $viewerId)) {
            return null;
        }

        return Storage::disk('public')->url($this->payment_qr_path);
    }

    private function viewerMaySeePaymentQr(int $viewerId): bool
    {
        if ($viewerId === (int) $this->organizer_id) {
            return true;
        }

        if (! $this->relationLoaded('players')) {
            return false;
        }

        return $this->players->contains(
            fn ($player) => (int) $player->user_id === $viewerId
                && $player->status === MatchPlayerStatus::Confirmed
        );
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'scheduled_at' => 'datetime',
            'start_time' => 'datetime',
            'end_time' => 'datetime',
            'total_players' => 'integer',
            'missing_players' => 'integer',
            'max_players' => 'integer',
            'gender' => MatchGender::class,
            'status' => MatchStatus::class,
        ];
    }
}