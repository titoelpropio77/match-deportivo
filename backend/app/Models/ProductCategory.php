<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Kind of product a store sells (balones, calzado, bebidas...), managed from the admin panel.
 */
class ProductCategory extends Model
{
    protected $fillable = [
        'key',
        'name',
        'icon',
    ];

    public function stores(): BelongsToMany
    {
        return $this->belongsToMany(Store::class);
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }
}
