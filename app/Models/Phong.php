<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\TrangThaiPhong;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Phong extends Model
{
    use HasFactory;

    protected $table = 'phong';

    protected $fillable = [
        'khu_nha_id',
        'ten_phong',
        'dien_tich',
        'so_nguoi_toi_da',
        'gia_hien_tai',
        'trang_thai',
        'mo_ta',
    ];

    protected $casts = [
        'dien_tich'       => 'decimal:2',
        'so_nguoi_toi_da' => 'integer',
        'gia_hien_tai'    => 'integer',
        'trang_thai'      => TrangThaiPhong::class,
        'created_at'      => 'datetime',
        'updated_at'      => 'datetime',
    ];

    // ===== Relationships =====

    /**
     * Phòng thuộc về một khu nhà.
     */
    public function khuNha(): BelongsTo
    {
        return $this->belongsTo(KhuNha::class);
    }

    /**
     * Phòng có nhiều bản ghi thuê qua các thời kỳ.
     */
    public function banGhiThue(): HasMany
    {
        return $this->hasMany(BanGhiThue::class);
    }

    /**
     * Lịch sử thay đổi giá phòng.
     */
    public function lichSuGiaPhong(): HasMany
    {
        return $this->hasMany(LichSuGiaPhong::class);
    }

    // ===== Scopes =====

    public function scopeTrong(\Illuminate\Database\Eloquent\Builder $query): void
    {
        $query->where('trang_thai', TrangThaiPhong::Trong);
    }

    public function scopeDangThue(\Illuminate\Database\Eloquent\Builder $query): void
    {
        $query->where('trang_thai', TrangThaiPhong::DangThue);
    }
}
