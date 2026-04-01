<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\MeterType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MeterReading extends Model
{
    use HasFactory;

    const UPDATED_AT = null;

    protected $table = 'meter_readings';

    protected $fillable = [
        'lease_id',
        'invoice_id',
        'type',
        'previous_reading',
        'current_reading',
        'meter_image',
        'reading_date',
    ];

    protected $casts = [
        'type'             => MeterType::class,
        'previous_reading' => 'integer',
        'current_reading'  => 'integer',
        'reading_date'     => 'date',
        'created_at'       => 'datetime',
    ];

    // ===== Relationships =====

    /**
     * Chỉ số thuộc hợp đồng thuê nào.
     */
    public function lease(): BelongsTo
    {
        return $this->belongsTo(Lease::class);
    }

    /**
     * Chỉ số gắn với hóa đơn nào (nullable — có thể ghi trước khi tạo hóa đơn).
     */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }
}
