<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\FreeUnitType;
use App\Enums\ServiceType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ServicePrice extends Model
{
    use HasFactory;

    protected $table = 'service_prices';

    protected $fillable = [
        'property_id',
        'service_type',
        'unit_price',
        'free_units',
        'free_unit_type',
        'effective_date',
        'expiry_date',
        'note',
    ];

    protected $casts = [
        'service_type'   => ServiceType::class,
        'free_unit_type' => FreeUnitType::class,
        'unit_price'     => 'integer',
        'free_units'     => 'integer',
        'effective_date' => 'date',
        'expiry_date'    => 'date',
    ];

    // ===== Relationships =====

    /**
     * Dịch vụ áp dụng cho khu nhà cụ thể (null = áp dụng toàn hệ thống).
     */
    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    /**
     * Lịch sử thay đổi đơn giá dịch vụ.
     */
    public function priceHistories(): HasMany
    {
        return $this->hasMany(ServicePriceHistory::class);
    }

    /**
     * Các chi tiết hóa đơn sử dụng mức giá này.
     */
    public function invoiceItems(): HasMany
    {
        return $this->hasMany(InvoiceItem::class);
    }

    // ===== Scopes =====

    public function scopeByType(\Illuminate\Database\Eloquent\Builder $query, ServiceType $type): void
    {
        $query->where('service_type', $type);
    }

    public function scopeGlobal(\Illuminate\Database\Eloquent\Builder $query): void
    {
        $query->whereNull('property_id');
    }
}
