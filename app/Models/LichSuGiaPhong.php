<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LichSuGiaPhong extends Model
{
    // Bảng chỉ có created_at, không có updated_at
    const UPDATED_AT = null;

    protected $table = 'lich_su_gia_phong';

    protected $fillable = [
        'phong_id',
        'user_id',
        'gia_cu',
        'gia_moi',
        'ngay_hieu_luc',
        'ghi_chu',
    ];

    protected $casts = [
        'gia_cu'        => 'integer',
        'gia_moi'       => 'integer',
        'ngay_hieu_luc' => 'date',
        'created_at'    => 'datetime',
    ];

    // ===== Relationships =====

    /**
     * Bản ghi lịch sử thuộc về phòng nào.
     */
    public function phong(): BelongsTo
    {
        return $this->belongsTo(Phong::class);
    }

    /**
     * Người thực hiện thay đổi giá (audit trail).
     * Nullable — nếu user đã xóa thì vẫn giữ bản ghi.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
