<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AccountingLedger extends Model
{
    use HasFactory;

    protected $table = 'accounting_ledgers';

    protected $fillable = [
        'user_id',
        'property_id',
        'period_type',
        'period_year',
        'period_month',
        'total_revenue',
        'note',
        'created_by',
    ];

    protected $casts = [
        'user_id' => 'integer',
        'property_id' => 'integer',
        'period_year' => 'integer',
        'period_month' => 'integer',
        'total_revenue' => 'integer',
        'created_by' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    // ===== Relationships =====

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Lấy danh sách chi tiết các dòng trong sổ.
     */
    public function details(): HasMany
    {
        return $this->hasMany(AccountingLedgerDetail::class, 'accounting_ledger_id');
    }

    // ===== Scopes =====
    // có thể dùng scope để lọc theo user_id, property_id, period_year, period_month, period_type
    public function scopeForUser(Builder $query, int $userId): void
    {
        $query->where('user_id', $userId);
    }
}