<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * Sitio de WordPress del cliente. El token se guarda solo como hash SHA-256.
 */
#[Fillable(['project_id', 'url', 'token_hash', 'last_sync_at'])]
#[Hidden(['token_hash'])]
class Site extends Model
{
    protected function casts(): array
    {
        return ['last_sync_at' => 'datetime'];
    }

    public static function hashToken(string $token): string
    {
        return hash('sha256', $token);
    }

    /** Genera un token nuevo, guarda su hash y devuelve el token en claro (se muestra una sola vez). */
    public function issueToken(): string
    {
        $token = Str::random(48);
        $this->token_hash = self::hashToken($token);
        $this->save();

        return $token;
    }

    public static function findByToken(string $token): ?self
    {
        return self::where('token_hash', self::hashToken($token))->first();
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function signals(): HasMany
    {
        return $this->hasMany(Signal::class);
    }
}
