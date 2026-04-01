<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\HinhThucThanhToan;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ThanhToan extends Model
{
    use HasFactory;

    /**
     * Bảng chỉ ghi thêm (append-only), không có cột updated_at.
     */
    const UPDATED_AT = null;

    protected $table = 'thanh_toan';

    protected $fillable = [
        'hoa_don_id',
        'tai_khoan_id',
        'so_tien',
        'hinh_thuc',
        'ngay_thanh_toan',
        'ma_giao_dich',
        'ghi_chu',
    ];

    protected $casts = [
        'so_tien'        => 'integer',
        'hinh_thuc'      => HinhThucThanhToan::class,
        'ngay_thanh_toan' => 'date',
        'created_at'     => 'datetime',
    ];

    // ===== Relationships =====

    /**
     * Giao dịch thanh toán cho hóa đơn nào.
     */
    public function hoaDon(): BelongsTo
    {
        return $this->belongsTo(HoaDon::class);
    }

    /**
     * Tài khoản ngân hàng nhận thanh toán (nullable — tiền mặt không có tài khoản).
     */
    public function taiKhoanNganHang(): BelongsTo
    {
        return $this->belongsTo(TaiKhoanNganHang::class, 'tai_khoan_id');
    }
}
