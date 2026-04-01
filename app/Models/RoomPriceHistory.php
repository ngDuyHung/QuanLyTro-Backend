<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RoomPriceHistory extends Model
{
    // Bảng chỉ có created_at, không có updated_at
    const UPDATED_AT = null;

    protected $table = 'room_price_histories';

    protected $fillable = [
        'room_id',
        'user_id',
        'old_price',
        'new_price',
        'effective_date',
        'note',
    ];

    protected $casts = [
        'old_price'      => 'integer',
        'new_price'      => 'integer',
        'effective_date' => 'date',
        'created_at'     => 'datetime',
    ];

    // ===== Relationships =====

    /**
     * Bản ghi lịch sử thuộc về phòng nào.
     */
    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    /**
     * Người thực hiện thay đổi giá (audit trail, nullable).
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
