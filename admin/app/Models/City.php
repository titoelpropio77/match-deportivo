<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Bolivian city (table owned by the backend migrations). Used to segment courts.
 */
class City extends Model
{
    protected $fillable = ['key', 'name', 'department', 'latitude', 'longitude', 'is_active'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'is_active' => 'boolean',
        ];
    }

    public function courts(): HasMany
    {
        return $this->hasMany(Court::class);
    }
}
