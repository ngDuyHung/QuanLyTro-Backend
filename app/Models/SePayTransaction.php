<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SePayTransaction extends Model
{
    use HasFactory;

    protected $table = 'sepay_transactions';

    protected $fillable = [
        'bank_account_id',

        'provider_transaction_id',
        'reference_code',
        'gateway',

        'transaction_time',
        'received_at',
        'processed_at',

        'account_number',
        'sub_account',

        'code',
        'content',
        'description',
        'matched_payment_code',

        'transfer_type',
        'transfer_amount',
        'accumulated',

        'match_status',
        'matched_amount',

        'error_message',
        'raw_payload',
    ];

    protected $casts = [
        'bank_account_id' => 'integer',

        'transaction_time' => 'datetime',
        'received_at' => 'datetime',
        'processed_at' => 'datetime',

        'transfer_amount' => 'integer',
        'accumulated' => 'integer',
        'matched_amount' => 'integer',

        'raw_payload' => 'array',

        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    // ===== Relationships =====

    /**
     * Tài khoản ngân hàng được SePay báo giao dịch.
     */
    public function bankAccount(): BelongsTo
    {
        return $this->belongsTo(BankAccount::class);
    }

    /**
     * Các dòng thu chi được tạo từ giao dịch SePay này.
     *
     * Thường là một dòng, nhưng nếu sau này cần tách/điều chỉnh thì vẫn hỗ trợ nhiều dòng.
     */
    public function financialTransactions(): HasMany
    {
        return $this->hasMany(FinancialTransaction::class);
    }

    // ===== Scopes =====

    public function scopeIncoming(Builder $query): void
    {
        $query->where('transfer_type', 'in');
    }

    public function scopeOutgoing(Builder $query): void
    {
        $query->where('transfer_type', 'out');
    }

    public function scopeUnmatched(Builder $query): void
    {
        $query->where('match_status', 'unmatched');
    }

    public function scopeNeedReview(Builder $query): void
    {
        $query->where('match_status', 'need_review');
    }

    public function scopeMatched(Builder $query): void
    {
        $query->where('match_status', 'matched');
    }

    // ===== Helpers =====

    public function isIncoming(): bool
    {
        return $this->transfer_type === 'in';
    }

    public function isMatched(): bool
    {
        return $this->match_status === 'matched';
    }

    public function isNeedReview(): bool
    {
        return $this->match_status === 'need_review';
    }

    public function getUnmatchedAmountAttribute(): int
    {
        return max(0, (int) $this->transfer_amount - (int) $this->matched_amount);
    }
}