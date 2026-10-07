<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

#[Fillable(['name', 'email', 'password', 'is_active', 'email_verified_at'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasRoles, Notifiable;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Cuenta habilitada. Las desactivadas conservan sus datos pero no
     * pueden iniciar sesión ni seguir en sesiones activas.
     */
    public function isActive(): bool
    {
        return (bool) $this->is_active;
    }

    /**
     * Cuentas que aún pueden iniciar sesión. Sirve para bloquear operaciones
     * que dejarían a todos los administradores fuera del sistema.
     */
    public static function activeCount(): int
    {
        return static::query()->where('is_active', true)->count();
    }

    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    /**
     * Oportunidades de las que la cuenta es responsable. La usa el filtro por
     * responsable y la columna queda en null al borrar la cuenta para no
     * romper el borrado.
     *
     * @return HasMany<Opportunity, $this>
     */
    public function opportunities(): HasMany
    {
        return $this->hasMany(Opportunity::class);
    }

    /**
     * Actividades de las que la cuenta es responsable. La usa el filtro por
     * responsable y la columna queda en null al borrar la cuenta para no
     * romper el borrado.
     *
     * @return HasMany<Activity, $this>
     */
    public function activities(): HasMany
    {
        return $this->hasMany(Activity::class);
    }
}
