<?php

declare(strict_types=1);

namespace App\Models;

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
    ];

    // ===== Relationships =====

    /**
     * Tài khoản ngân hàng thuộc người dùng nào.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Các giao dịch thanh toán qua tài khoản này.
     */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    // ===== Scopes =====

    public function scopeDefault(\Illuminate\Database\Eloquent\Builder $query): void
    {
        $query->where('is_default', true);
    }
}
