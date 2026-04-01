<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\LoaiChiSo;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChiSoDienNuoc extends Model
{
    use HasFactory;

    /**
     * Bảng chỉ ghi thêm (append-only), không có cột updated_at.
     */
    const UPDATED_AT = null;

    protected $table = 'chi_so_dien_nuoc';

    protected $fillable = [
        'ban_ghi_thue_id',
        'hoa_don_id',
        'loai',
        'chi_so_cu',
        'chi_so_moi',
        'ngay_ghi',
        'ghi_chu',
    ];

    protected $casts = [
        'loai'      => LoaiChiSo::class,
        'chi_so_cu' => 'integer',
        'chi_so_moi' => 'integer',
        'ngay_ghi'  => 'date',
        'created_at' => 'datetime',
    ];

    // ===== Relationships =====

    /**
     * Chỉ số thuộc bản ghi thuê nào.
     */
    public function banGhiThue(): BelongsTo
    {
        return $this->belongsTo(BanGhiThue::class);
    }

    /**
     * Chỉ số gắn với hóa đơn nào (nullable — có thể ghi trước khi tạo hóa đơn).
     */
    public function hoaDon(): BelongsTo
    {
        return $this->belongsTo(HoaDon::class);
    }
}
