<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AccountingLedgerDetail extends Model
{
    use HasFactory;

    protected $table = 'accounting_ledger_details';

    protected $fillable = [
        'accounting_ledger_id',
        'transaction_date',
        'transaction_code',
        'description',
        'amount',
    ];

    protected $casts = [
        'accounting_ledger_id' => 'integer',
        'transaction_date' => 'date',
        'amount' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    // ===== Relationships =====

    /**
     * Dòng chi tiết này thuộc về lần chốt sổ nào.
     */
    public function ledger(): BelongsTo
    {
        return $this->belongsTo(AccountingLedger::class, 'accounting_ledger_id');
    }
}
