<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'nickname', 'email', 'phone', 'preferred_position', 'gender', 'avatar_path', 'password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * Get the matches organized by the user.
     */
    public function organizedMatches(): HasMany
    {
        return $this->hasMany(MatchModel::class, 'organizer_id');
    }

    /**
     * Get the user's match registrations.
     */
    public function matchPlayers(): HasMany
    {
        return $this->hasMany(MatchPlayer::class);
    }

    /**
     * Get the courts created by the user.
     */
    public function courts(): HasMany
    {
        return $this->hasMany(Court::class, 'owner_id');
    }

    /**
     * Get the players this user always accepts into their matches.
     */
    public function trustedPlayers(): HasMany
    {
        return $this->hasMany(TrustedPlayer::class, 'organizer_id');
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }
}
