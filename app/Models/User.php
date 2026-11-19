<?php

namespace App\Models;

use App\Enums\Perfil;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = ['name', 'email', 'password', 'perfil', 'funcionario_id', 'ativo'];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'perfil' => Perfil::class,
            'ativo' => 'boolean',
        ];
    }

    public function funcionario(): BelongsTo
    {
        return $this->belongsTo(Funcionario::class);
    }

    public function temPerfil(Perfil|string ...$perfis): bool
    {
        foreach ($perfis as $p) {
            if ($this->perfil === ($p instanceof Perfil ? $p : Perfil::from($p))) {
                return true;
            }
        }

        return false;
    }

    public function ehMecanico(): bool
    {
        return $this->perfil === Perfil::Mecanico;
    }

    /** Equipe que recebe os alertas operacionais */
    public function scopeEquipeEscritorio(Builder $q): Builder
    {
        return $q->where('ativo', true)->whereIn('perfil', [Perfil::Admin->value, Perfil::Atendente->value]);
    }
}
