<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FinancialTransaction extends Model
{
    use HasFactory;

    protected $table = 'financial_transactions';

    protected $fillable = [
        'property_id',
        'room_id',
        'lease_id',
        'tenant_id',
        'bank_account_id',
        'sepay_transaction_id',

        'transaction_code',

        'direction',
        'category',
        'accounting_type',

        'amount',
        'method',
        'status',

        'transaction_date',

        'transfer_content',
        'bank_transaction_code',

        'confirmed_at',
        'cancelled_at',
        'cancel_reason',

        'description',
        'note',

        'created_by',
    ];

    protected $casts = [
        'property_id' => 'integer',
        'room_id' => 'integer',
        'lease_id' => 'integer',
        'tenant_id' => 'integer',
        'bank_account_id' => 'integer',
        'sepay_transaction_id' => 'integer',

        'amount' => 'integer',

        'transaction_date' => 'datetime',
        'confirmed_at' => 'datetime',
        'cancelled_at' => 'datetime',

        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    // ===== Relationships =====

    /**
     * Giao dịch thu/chi thuộc khu nhà nào.
     */
    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    /**
     * Giao dịch thu/chi liên quan đến phòng nào, nếu có.
     */
    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    /**
     * Giao dịch thu/chi liên quan đến hợp đồng nào, nếu có.
     */
    public function lease(): BelongsTo
    {
        return $this->belongsTo(Lease::class);
    }

    /**
     * Giao dịch thu/chi liên quan đến khách thuê nào, nếu có.
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Tài khoản ngân hàng nhận/chuyển tiền.
     */
    public function bankAccount(): BelongsTo
    {
        return $this->belongsTo(BankAccount::class);
    }

    /**
     * Giao dịch SePay gốc nếu khoản thu này đến từ webhook SePay.
     */
    public function sepayTransaction(): BelongsTo
    {
        return $this->belongsTo(SePayTransaction::class, 'sepay_transaction_id');
    }

    /**
     * Người tạo/xác nhận giao dịch.
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Các bản ghi cấn tiền vào hóa đơn.
     */
    public function allocations(): HasMany
    {
        return $this->hasMany(FinancialTransactionAllocation::class);
    }

    /**
     * Các hóa đơn được giao dịch này cấn tiền vào.
     */
    public function invoices(): BelongsToMany
    {
        return $this->belongsToMany(
            Invoice::class,
            'financial_transaction_allocations',
            'financial_transaction_id',
            'invoice_id'
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

    public function scopeIncome(Builder $query): void
    {
        $query->where('direction', 'income');
    }

    public function scopeExpense(Builder $query): void
    {
        $query->where('direction', 'expense');
    }

    public function scopeConfirmed(Builder $query): void
    {
        $query->where('status', 'confirmed');
    }

    public function scopeByProperty(Builder $query, int $propertyId): void
    {
        $query->where('property_id', $propertyId);
    }

    public function scopeByCategory(Builder $query, string $category): void
    {
        $query->where('category', $category);
    }

    public function scopeBetweenDates(Builder $query, string $from, string $to): void
    {
        $query->whereBetween('transaction_date', [$from, $to]);
    }

    // ===== Helpers =====

    public function isIncome(): bool
    {
        return $this->direction === 'income';
    }

    public function isExpense(): bool
    {
        return $this->direction === 'expense';
    }

    public function isConfirmed(): bool
    {
        return $this->status === 'confirmed';
    }
}
