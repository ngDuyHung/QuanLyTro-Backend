<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\InvoiceStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Invoice extends Model
{
    use HasFactory;

    protected $table = 'invoices';

    protected $fillable = [
        'lease_id',
        'invoice_code',
        'period_from',
        'period_to',
        'total_amount',
        'status',
    ];

    protected $casts = [
        'period_from' => 'date',
        'period_to'   => 'date',
        'total_amount' => 'decimal:2',
        'status'      => InvoiceStatus::class,
    ];

    // ===== Relationships =====

    /**
     * Hóa đơn thuộc hợp đồng thuê nào.
     */
    public function lease(): BelongsTo
    {
        return $this->belongsTo(Lease::class);
    }

    /**
     * Các chi tiết dòng phí của hóa đơn.
     */
    public function items(): HasMany
    {
        return $this->hasMany(InvoiceItem::class);
    }

    /**
     * Các bản ghi chỉ số điện nước gắn với kỳ hóa đơn này.
     */
    public function meterReadings(): HasMany
    {
        return $this->hasMany(MeterReading::class);
    }

    /**
     * Các giao dịch thanh toán cho hóa đơn.
     */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    // ===== Scopes =====

    public function scopeUnpaid(\Illuminate\Database\Eloquent\Builder $query): void
    {
        $query->where('status', InvoiceStatus::Unpaid);
    }

    public function scopePaid(\Illuminate\Database\Eloquent\Builder $query): void
    {
        $query->where('status', InvoiceStatus::Paid);
    }
}
