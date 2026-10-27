<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    // role, client_id y google_id no son asignables en masa: se fijan a mano.
    // prospect: se registró y puede mandar un pedido. client: la agencia aprobó su pedido.
    public const ROLE_PROSPECT = 'prospect';

    public const ROLE_CLIENT = 'client';

    public const ROLE_AGENCY = 'agency';

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

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function isAgency(): bool
    {
        return $this->role === self::ROLE_AGENCY;
    }

    /** Pantalla de inicio según el rol. */
    public function homeRoute(): string
    {
        return match ($this->role) {
            self::ROLE_AGENCY => 'agency.home',
            self::ROLE_CLIENT => 'client.home',
            default => 'request.create',
        };
    }
}
