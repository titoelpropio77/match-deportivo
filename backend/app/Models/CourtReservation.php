<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

class CourtReservation extends Model
{
    protected $fillable = [
        'court_field_id',
        'user_id',
        'sport_id',
        'reserved_on',
        'starts_at',
        'ends_at',
        'hours',
        'amount',
        'status',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'reserved_on' => 'date',
            'hours' => 'integer',
            'amount' => 'decimal:2',
        ];
    }

    public function field(): BelongsTo
    {
        return $this->belongsTo(CourtField::class, 'court_field_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function sport(): BelongsTo
    {
        return $this->belongsTo(Sport::class);
    }

    public function overlaps(Carbon $start, Carbon $end): bool
    {
        $date = $start->toDateString();
        $reservedStart = Carbon::parse($date.' '.$this->starts_at);
        $reservedEnd = Carbon::parse($date.' '.$this->ends_at);

        return $reservedStart->lt($end) && $reservedEnd->gt($start);
    }
}
