<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MatchLevel extends Model
{
    protected $fillable = ['key', 'name', 'order'];

    public function matches(): HasMany
    {
        return $this->hasMany(MatchModel::class, 'level_id');
    }
}
