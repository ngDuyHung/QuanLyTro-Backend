<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FinancialTransactionAllocation extends Model
{
    use HasFactory;

    protected $table = 'financial_transaction_allocations';

    protected $fillable = [
        'financial_transaction_id',
        'invoice_id',
        'allocated_amount',
        'allocation_type',
        'allocated_at',
        'created_by',
        'note',
    ];

    protected $casts = [
        'financial_transaction_id' => 'integer',
        'invoice_id' => 'integer',
        'allocated_amount' => 'integer',
        'allocated_at' => 'datetime',
        'created_by' => 'integer',

        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    // ===== Relationships =====

    /**
     * Khoản thu/chi được dùng để cấn vào hóa đơn.
     *
     * Tên function này phải là financialTransaction()
     * vì service đang gọi relationship này.
     */
    public function financialTransaction(): BelongsTo
    {
        return $this->belongsTo(FinancialTransaction::class);
    }

    /**
     * Hóa đơn được cấn tiền.
     */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    /**
     * Người thực hiện cấn tiền.
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // ===== Scopes =====

    public function scopePayment(Builder $query): void
    {
        $query->where('allocation_type', 'payment');
    }

    public function scopeRefund(Builder $query): void
    {
        $query->where('allocation_type', 'refund');
    }

    public function scopeAdjustment(Builder $query): void
    {
        $query->where('allocation_type', 'adjustment');
    }
}