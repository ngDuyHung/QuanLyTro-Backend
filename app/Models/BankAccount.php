<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BankAccount extends Model
{
    use HasFactory;

    protected $table = 'bank_accounts';

    protected $fillable = [
        'user_id',
        'account_name',
        'account_number',
        'bank_name',
        'bank_code',
        'branch',
        'is_default',
    ];

    protected $casts = [
        'is_default' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    // ===== Relationships =====

    /**
     * Tài khoản ngân hàng thuộc người dùng/chủ trọ nào.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Các giao dịch thu/chi đi qua tài khoản ngân hàng này.
     */
    public function financialTransactions(): HasMany
    {
        return $this->hasMany(FinancialTransaction::class);
    }

    /**
     * Các giao dịch webhook SePay báo về cho tài khoản này.
     */
    public function sepayTransactions(): HasMany
    {
        return $this->hasMany(SePayTransaction::class);
    }

    // ===== Scopes =====

    public function scopeDefault(Builder $query): void
    {
        $query->where('is_default', true);
    }

    public function scopeByAccountNumber(Builder $query, string $accountNumber): void
    {
        $query->where('account_number', $accountNumber);
    }
}