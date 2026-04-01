<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\LoaiPhi;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChiTietHoaDon extends Model
{
    use HasFactory;

    /**
     * Bảng chỉ ghi thêm (append-only), không có cột updated_at.
     */
    const UPDATED_AT = null;

    protected $table = 'chi_tiet_hoa_don';

    protected $fillable = [
        'hoa_don_id',
        'dich_vu_gia_id',
        'loai_phi',
        'mo_ta',
        'don_gia_snapshot',
        'so_luong',
        'tong_tien',
    ];

    protected $casts = [
        'loai_phi'         => LoaiPhi::class,
        'don_gia_snapshot' => 'integer',
        'so_luong'         => 'decimal:2',
        'tong_tien'        => 'integer',
        'created_at'       => 'datetime',
    ];

    // ===== Relationships =====

    /**
     * Chi tiết thuộc hóa đơn nào.
     */
    public function hoaDon(): BelongsTo
    {
        return $this->belongsTo(HoaDon::class);
    }

    /**
     * Mức giá dịch vụ tại thời điểm xuất hóa đơn (nullable — tiền phòng không có dv_gia).
     */
    public function dichVuGia(): BelongsTo
    {
        return $this->belongsTo(DichVuGia::class);
    }
}
