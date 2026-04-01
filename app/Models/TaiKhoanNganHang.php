<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TaiKhoanNganHang extends Model
{
    use HasFactory;

    protected $table = 'tai_khoan_ngan_hang';

    protected $fillable = [
        'user_id',
        'ten_ngan_hang',
        'so_tai_khoan',
        'ten_chu_tai_khoan',
        'chi_nhanh',
        'is_default',
    ];

    protected $casts = [
        'is_default' => 'boolean',
    ];

    // ===== Relationships =====

    /**
     * Tài khoản ngân hàng thuộc người dùng nào.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Các giao dịch thanh toán qua tài khoản này.
     */
    public function thanhToan(): HasMany
    {
        return $this->hasMany(ThanhToan::class);
    }

    // ===== Scopes =====

    public function scopeDefault(\Illuminate\Database\Eloquent\Builder $query): void
    {
        $query->where('is_default', true);
    }
}
