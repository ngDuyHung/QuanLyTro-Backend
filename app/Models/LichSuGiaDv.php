<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LichSuGiaDv extends Model
{
    use HasFactory;

    /**
     * Bảng chỉ ghi thêm (append-only), không có cột updated_at.
     */
    const UPDATED_AT = null;

    protected $table = 'lich_su_gia_dv';

    protected $fillable = [
        'dich_vu_gia_id',
        'user_id',
        'don_gia_cu',
        'don_gia_moi',
        'ngay_thay_doi',
        'ghi_chu',
    ];

    protected $casts = [
        'don_gia_cu'   => 'integer',
        'don_gia_moi'  => 'integer',
        'ngay_thay_doi' => 'date',
        'created_at'   => 'datetime',
    ];

    // ===== Relationships =====

    /**
     * Lịch sử thuộc mức giá dịch vụ nào.
     */
    public function dichVuGia(): BelongsTo
    {
        return $this->belongsTo(DichVuGia::class);
    }

    /**
     * Người thực hiện thay đổi giá (audit trail, nullable).
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
