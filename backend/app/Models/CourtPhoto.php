<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CourtPhoto extends Model
{
    protected $fillable = ['court_id', 'url', 'order'];

    public function court(): BelongsTo
    {
        return $this->belongsTo(Court::class);
    }
}
