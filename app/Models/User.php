<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

/**
 * Akun login. Data pegawai terpisah (tabel employees).
 * Hak akses berbasis peran: config/fnb.php → roles.
 * @2026-Alifiandra
 */
class User extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $fillable = ['tenant_id', 'branch_id', 'name', 'email', 'phone', 'role', 'theme_mode', 'is_active', 'password'];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'is_active' => 'boolean',
            'password' => 'hashed',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function employee()
    {
        return $this->hasOne(Employee::class);
    }

    /** Daftar kemampuan (ability) peran pengguna. */
    public function abilities(): array
    {
        $map = config('fnb.roles', []);

        return $map[$this->role]['abilities'] ?? [];
    }

    public function hasAbility(string $ability): bool
    {
        $abilities = $this->abilities();

        return in_array('*', $abilities, true) || in_array($ability, $abilities, true);
    }

    public function roleLabel(): string
    {
        return config("fnb.roles.{$this->role}.label", ucfirst($this->role));
    }

    /** null branch_id = boleh semua cabang tenant. */
    public function canAccessBranch(int $branchId): bool
    {
        return $this->branch_id === null || (int) $this->branch_id === $branchId;
    }
}
