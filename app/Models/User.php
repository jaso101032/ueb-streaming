<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'password', 'role', 'operator_permissions', 'chat_restricted'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    protected $attributes = ['notify_live' => true, 'notify_videos' => true];

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
            'operator_permissions' => 'array',
            'chat_restricted' => 'boolean',
            'notify_live' => 'boolean',
            'notify_videos' => 'boolean',
        ];
    }

    /**
     * Métodos de verificación de roles del sistema UEB Streaming.
     */
    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    public function isOperador(): bool
    {
        return $this->role === 'operador';
    }

    public function allowsOperatorAction(string $action): bool
    {
        if ($this->isAdmin()) return true;
        if (! $this->isOperador()) return false;

        // Each operator acts only with the permissions explicitly assigned. The
        // 2026-11-10 migration made the historical implicit rights explicit.
        return in_array($action, $this->operator_permissions ?? [], true);
    }

    public static function operatorActions(): array
    {
        return ['events', 'stream', 'moderate_chat', 'toggle_chat'];
    }

    public function isUsuario(): bool
    {
        return in_array(strtolower(trim((string) $this->role)), ['usuario', 'user', ''], true);
    }
}
