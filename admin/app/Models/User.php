<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

/**
 * Same `users` table as the API backend. Roles/permissions are managed only from the admin panel.
 */
#[Fillable(['name', 'nickname', 'email', 'phone', 'preferred_position', 'gender', 'password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasRoles, Notifiable;

    public const GENDERS = [
        'male' => 'Masculino',
        'female' => 'Femenino',
    ];

    /**
     * Shown next to each role when assigning roles to a user.
     *
     * @var array<string, string>
     */
    public const ROLE_DESCRIPTIONS = [
        'superadmin' => 'Acceso total al sistema, incluida la configuración de roles y permisos.',
        'admin' => 'Personal de la plataforma: todos los centros deportivos, reservas y usuarios.',
        'partner' => 'Dueño de centros deportivos: administra solo los suyos, sus reservas y managers.',
        'manager' => 'Encargado asignado por un partner: gestiona las canchas y reservas de ese centro.',
        'cliente' => 'Jugador de la app, sin acceso al panel.',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function courts(): HasMany
    {
        return $this->hasMany(Court::class, 'owner_id');
    }

    /**
     * Venues this user was assigned to as manager.
     */
    public function managedCourts(): BelongsToMany
    {
        return $this->belongsToMany(Court::class, 'court_managers')->withTimestamps();
    }

    public function organizedMatches(): HasMany
    {
        return $this->hasMany(MatchModel::class, 'organizer_id');
    }
}
