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
        'base_price',
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
        'base_price'     => 'integer',
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

    /**
     * Lấy toàn bộ bảng giá dịch vụ áp dụng cho một khu nhà cụ thể (Đã gộp giá riêng biệt và giá mặc định).
     * * @return \Illuminate\Support\Collection<string, ServicePrice>
     */
    public static function getApplicablePrices(int $propertyId): \Illuminate\Support\Collection
    {
        // 1. Lấy tất cả giá cấu hình riêng của khu nhà hiện tại
        $propertyPrices = self::where('property_id', $propertyId)
            ->get()
            ->keyBy(fn($p) => $p->service_type->value);

        $assignedTypes = $propertyPrices->keys()->all();

        // 2. Lấy giá mặc định hệ thống đối với những loại dịch vụ chưa cấu hình riêng
        $globalPrices = self::whereNull('property_id')
            ->when(!empty($assignedTypes), fn($q) => $q->whereNotIn('service_type', $assignedTypes))
            ->get()
            ->keyBy(fn($p) => $p->service_type->value);

        // 3. Kết hợp lại và trả về Collection định dạng key là loại dịch vụ
        return $propertyPrices->merge($globalPrices);
    }
}
