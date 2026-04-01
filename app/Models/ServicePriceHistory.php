<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ServicePriceHistory extends Model
{
    use HasFactory;

    const UPDATED_AT = null;

    protected $table = 'service_price_histories';

    protected $fillable = [
        'service_price_id',
        'user_id',
        'old_price',
        'new_price',
        'changed_date',
        'reason',
    ];

    protected $casts = [
        'old_price'    => 'integer',
        'new_price'    => 'integer',
        'changed_date' => 'date',
        'created_at'   => 'datetime',
    ];

    // ===== Relationships =====

    /**
     * Lịch sử thuộc mức giá dịch vụ nào.
     */
    public function servicePrice(): BelongsTo
    {
        return $this->belongsTo(ServicePrice::class);
    }

    /**
     * Người thực hiện thay đổi giá (audit trail, nullable).
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
