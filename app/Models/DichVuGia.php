<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\KieuMienPhi;
use App\Enums\LoaiDichVu;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DichVuGia extends Model
{
    use HasFactory;

    protected $table = 'dich_vu_gia';

    protected $fillable = [
        'khu_nha_id',
        'loai_dich_vu',
        'don_gia',
        'kieu_mien_phi',
        'don_vi_mien_phi',
        'ngay_hieu_luc',
        'ngay_het_hieu_luc',
        'ghi_chu',
    ];

    protected $casts = [
        'loai_dich_vu'      => LoaiDichVu::class,
        'kieu_mien_phi'     => KieuMienPhi::class,
        'don_gia'           => 'integer',
        'don_vi_mien_phi'   => 'integer',
        'ngay_hieu_luc'     => 'date',
        'ngay_het_hieu_luc' => 'date',
    ];

    // ===== Relationships =====

    /**
     * Dịch vụ áp dụng cho khu nhà cụ thể (null = áp dụng toàn hệ thống).
     */
    public function khuNha(): BelongsTo
    {
        return $this->belongsTo(KhuNha::class);
    }

    /**
     * Lịch sử thay đổi đơn giá dịch vụ.
     */
    public function lichSuGiaDv(): HasMany
    {
        return $this->hasMany(LichSuGiaDv::class);
    }

    /**
     * Các chi tiết hóa đơn sử dụng mức giá này.
     */
    public function chiTietHoaDon(): HasMany
    {
        return $this->hasMany(ChiTietHoaDon::class);
    }

    // ===== Scopes =====

    /**
     * Lọc dịch vụ theo loại.
     */
    public function scopeByLoai(\Illuminate\Database\Eloquent\Builder $query, LoaiDichVu $loai): void
    {
        $query->where('loai_dich_vu', $loai);
    }

    /**
     * Chỉ lấy dịch vụ áp dụng toàn hệ thống (khu_nha_id = null).
     */
    public function scopeHeThong(\Illuminate\Database\Eloquent\Builder $query): void
    {
        $query->whereNull('khu_nha_id');
    }
}
