<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Invoice extends Model
{
    use HasFactory;

    protected $table = 'invoices';

    protected $fillable = [
        'lease_id',
        'property_id',
        'room_id',

        'invoice_code',
        'invoice_type',

        'period_from',
        'period_to',

        'issue_date',
        'due_date',

        'status',

        'subtotal_amount',
        'previous_debt_amount',
        'discount_amount',
        'surcharge_amount',
        'total_amount',

        'paid_amount',
        'remaining_amount',

        'issued_at',
        'locked_at',
        'cancelled_at',
        'cancel_reason',

        'note',
        'created_by',
    ];

    protected $casts = [
        'lease_id' => 'integer',
        'property_id' => 'integer',
        'room_id' => 'integer',

        'period_from' => 'date',
        'period_to' => 'date',
        'issue_date' => 'date',
        'due_date' => 'date',

        'subtotal_amount' => 'integer',
        'previous_debt_amount' => 'integer',
        'discount_amount' => 'integer',
        'surcharge_amount' => 'integer',
        'total_amount' => 'integer',
        'paid_amount' => 'integer',
        'remaining_amount' => 'integer',

        'issued_at' => 'datetime',
        'locked_at' => 'datetime',
        'cancelled_at' => 'datetime',

        'created_by' => 'integer',

        'created_at' => 'datetime',
        'updated_at' => 'datetime',
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
     * Khu nhà của hóa đơn.
     *
     * Lưu trực tiếp để lọc/báo cáo nhanh.
     */
    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    /**
     * Phòng của hóa đơn.
     *
     * Lưu trực tiếp để lọc/báo cáo nhanh.
     */
    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    /**
     * Người tạo hóa đơn.
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Các dòng chi tiết hóa đơn.
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
     * Các bản ghi cấn tiền vào hóa đơn.
     */
    public function allocations(): HasMany
    {
        return $this->hasMany(FinancialTransactionAllocation::class);
    }

    /**
     * Các giao dịch thu chi đã được cấn vào hóa đơn này.
     */
    public function financialTransactions(): BelongsToMany
    {
        return $this->belongsToMany(
            FinancialTransaction::class,
            'financial_transaction_allocations',
            'invoice_id',
            'financial_transaction_id'
        )
            ->withPivot([
                'allocated_amount',
                'allocation_type',
                'allocated_at',
                'created_by',
                'note',
            ])
            ->withTimestamps();
    }

    // ===== Scopes =====

    public function scopeDraft(Builder $query): void
    {
        $query->where('status', 'draft');
    }

    public function scopeIssued(Builder $query): void
    {
        $query->where('status', 'issued');
    }

    public function scopePartiallyPaid(Builder $query): void
    {
        $query->where('status', 'partially_paid');
    }

    public function scopePaid(Builder $query): void
    {
        $query->where('status', 'paid');
    }

    public function scopeOverdue(Builder $query): void
    {
        $query->where('status', 'overdue');
    }

    public function scopeCancelled(Builder $query): void
    {
        $query->where('status', 'cancelled');
    }

    public function scopeForProperty(Builder $query, int $propertyId): void
    {
        $query->where('property_id', $propertyId);
    }

    public function scopeForLease(Builder $query, int $leaseId): void
    {
        $query->where('lease_id', $leaseId);
    }

    // ===== Helpers =====

    public function isDraft(): bool
    {
        return $this->status === 'draft';
    }

    public function isIssued(): bool
    {
        return $this->status === 'issued';
    }

    public function isPaid(): bool
    {
        return $this->status === 'paid';
    }

    public function isCancelled(): bool
    {
        return $this->status === 'cancelled';
    }

    public function hasDebt(): bool
    {
        return (int) $this->remaining_amount > 0;
    }
}