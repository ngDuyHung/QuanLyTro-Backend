<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ChargeType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InvoiceItem extends Model
{
    use HasFactory;

    const UPDATED_AT = null;

    protected $table = 'invoice_items';

    protected $fillable = [
        'invoice_id',
        'service_price_id',
        'charge_type',
        'description',
        'unit_price_snapshot',
        'quantity',
        'total',
    ];

    protected $casts = [
        'charge_type'        => ChargeType::class,
        'unit_price_snapshot' => 'integer',
        'quantity'           => 'decimal:2',
        'total'              => 'integer',
        'created_at'         => 'datetime',
    ];

    // ===== Relationships =====

    /**
     * Chi tiết thuộc hóa đơn nào.
     */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    /**
     * Mức giá dịch vụ tại thời điểm xuất hóa đơn (nullable — tiền phòng không có service_price).
     */
    public function servicePrice(): BelongsTo
    {
        return $this->belongsTo(ServicePrice::class);
    }
}
