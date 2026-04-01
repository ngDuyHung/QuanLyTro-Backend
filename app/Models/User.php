<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\UserRole;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'phone',
        'role',
        'is_active',
        'password',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'is_active'         => 'boolean',
        'role'              => UserRole::class,
        'password'          => 'hashed',
    ];

    // ===== Relationships =====

    /**
     * Một chủ trọ (chu_tro) sở hữu nhiều khu nhà.
     */
    public function khuNha(): HasMany
    {
        return $this->hasMany(KhuNha::class);
    }

    /**
     * Tài khoản hệ thống liên kết với hồ sơ khách thuê (nếu có).
     */
    public function khachThue(): HasOne
    {
        return $this->hasOne(KhachThue::class);
    }

    /**
     * Chủ trọ có nhiều tài khoản ngân hàng để nhận tiền.
     */
    public function taiKhoanNganHang(): HasMany
    {
        return $this->hasMany(TaiKhoanNganHang::class);
    }

    // ===== Scopes =====

    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    public function scopeByRole(Builder $query, UserRole $role): void
    {
        $query->where('role', $role->value);
    }
}
