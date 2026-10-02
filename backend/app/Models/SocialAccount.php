<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A Google / Facebook account linked to a user (Laravel Socialite).
 */
#[Fillable(['user_id', 'provider', 'provider_id', 'email'])]
class SocialAccount extends Model
{
    public const PROVIDERS = ['google', 'facebook'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
