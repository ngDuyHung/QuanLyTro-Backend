<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\PaymentMethod;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    use HasFactory;

    const UPDATED_AT = null;

    protected $table = 'payments';

    protected $fillable = [
        'invoice_id',
        'bank_account_id',
        'amount',
        'method',
        'payment_date',
        'note',
    ];

    protected $casts = [
        'amount'       => 'integer',
        'method'       => PaymentMethod::class,
        'payment_date' => 'date',
        'created_at'   => 'datetime',
    ];

    // ===== Relationships =====

    /**
     * Giao dịch thanh toán cho hóa đơn nào.
     */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    /**
     * Tài khoản ngân hàng nhận thanh toán (nullable — tiền mặt không có tài khoản).
     */
    public function bankAccount(): BelongsTo
    {
        return $this->belongsTo(BankAccount::class);
    }
}
