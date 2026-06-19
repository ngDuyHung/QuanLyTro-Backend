<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InvoiceItem extends Model
{
    use HasFactory;

    protected $table = 'invoice_items';

    protected $fillable = [
        'invoice_id',
        'service_price_id',

        'charge_type',
        'description',
        'unit',

        'quantity',
        'unit_price_snapshot',
        'free_quantity_snapshot',
        'amount',

        'sort_order',
    ];

    protected $casts = [
        'invoice_id' => 'integer',
        'service_price_id' => 'integer',

        'quantity' => 'decimal:2',
        'unit_price_snapshot' => 'integer',
        'free_quantity_snapshot' => 'decimal:2',
        'amount' => 'integer',

        'sort_order' => 'integer',

        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    // ===== Relationships =====

    /**
     * Dòng chi tiết thuộc hóa đơn nào.
     */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    /**
     * Mức giá dịch vụ được dùng để tạo dòng hóa đơn này.
     *
     * Nullable vì:
     * - một số dòng là khoản thủ công,
     * - hóa đơn cũ vẫn phải giữ snapshot tiền kể cả khi bảng giá bị xóa.
     */
    public function servicePrice(): BelongsTo
    {
        return $this->belongsTo(ServicePrice::class);
    }

    // ===== Scopes =====

    public function scopeByChargeType(Builder $query, string $chargeType): void
    {
        $query->where('charge_type', $chargeType);
    }

    // ===== Helpers =====

    public function isDiscount(): bool
    {
        return $this->charge_type === 'discount';
    }

    public function isUtility(): bool
    {
        return in_array($this->charge_type, ['electricity', 'water'], true);
    }
}