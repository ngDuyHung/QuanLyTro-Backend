<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\TrangThaiHoaDon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class HoaDon extends Model
{
    use HasFactory;

    protected $table = 'hoa_don';

    protected $fillable = [
        'ban_ghi_thue_id',
        'ky_tu',
        'ky_den',
        'tong_tien',
        'trang_thai',
        'ghi_chu',
    ];

    protected $casts = [
        'ky_tu'     => 'date',
        'ky_den'    => 'date',
        'tong_tien' => 'integer',
        'trang_thai' => TrangThaiHoaDon::class,
    ];

    // ===== Relationships =====

    /**
     * Hóa đơn thuộc bản ghi thuê nào.
     */
    public function banGhiThue(): BelongsTo
    {
        return $this->belongsTo(BanGhiThue::class);
    }

    /**
     * Các chi tiết dòng phí của hóa đơn.
     */
    public function chiTietHoaDon(): HasMany
    {
        return $this->hasMany(ChiTietHoaDon::class);
    }

    /**
     * Các bản ghi chỉ số điện nước gắn với kỳ hóa đơn này.
     */
    public function chiSoDienNuoc(): HasMany
    {
        return $this->hasMany(ChiSoDienNuoc::class);
    }

    /**
     * Các giao dịch thanh toán cho hóa đơn.
     */
    public function thanhToan(): HasMany
    {
        return $this->hasMany(ThanhToan::class);
    }

    // ===== Scopes =====

    public function scopeChuaThanhToan(\Illuminate\Database\Eloquent\Builder $query): void
    {
        $query->where('trang_thai', TrangThaiHoaDon::ChuaThanhToan);
    }

    public function scopeDaThanhToan(\Illuminate\Database\Eloquent\Builder $query): void
    {
        $query->where('trang_thai', TrangThaiHoaDon::DaThanhToan);
    }
}
