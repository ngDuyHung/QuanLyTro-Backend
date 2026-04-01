<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\TrangThaiBanGhiThue;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BanGhiThue extends Model
{
    use HasFactory;

    protected $table = 'ban_ghi_thue';

    protected $fillable = [
        'phong_id',
        'khach_thue_id',
        'ngay_bat_dau',
        'ngay_ket_thuc',
        'ngay_thu_tien',
        'tien_coc',
        'ngay_bao_tra',
        'trang_thai',
    ];

    protected $casts = [
        'ngay_bat_dau'  => 'date',
        'ngay_ket_thuc' => 'date',
        'ngay_thu_tien' => 'integer',
        'tien_coc'      => 'integer',
        'ngay_bao_tra'  => 'date',
        'trang_thai'    => TrangThaiBanGhiThue::class,
        'created_at'    => 'datetime',
        'updated_at'    => 'datetime',
    ];

    // ===== Relationships =====

    /**
     * Bản ghi thuê thuộc về phòng nào.
     */
    public function phong(): BelongsTo
    {
        return $this->belongsTo(Phong::class);
    }

    /**
     * Bản ghi thuê thuộc về khách thuê nào (người ký hợp đồng).
     */
    public function khachThue(): BelongsTo
    {
        return $this->belongsTo(KhachThue::class);
    }

    /**
     * Các thành viên cùng ở trong phòng (không phải người ký HĐ).
     */
    public function thanhVienThue(): HasMany
    {
        return $this->hasMany(ThanhVienThue::class);
    }

    /**
     * Các hóa đơn theo từng kỳ.
     */
    public function hoaDon(): HasMany
    {
        return $this->hasMany(HoaDon::class);
    }

    /**
     * Các bản ghi chỉ số điện nước.
     */
    public function chiSoDienNuoc(): HasMany
    {
        return $this->hasMany(ChiSoDienNuoc::class);
    }

    // ===== Scopes =====

    public function scopeDangThue(\Illuminate\Database\Eloquent\Builder $query): void
    {
        $query->where('trang_thai', TrangThaiBanGhiThue::DangThue);
    }
}
