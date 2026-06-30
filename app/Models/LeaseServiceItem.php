<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ServiceType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LeaseServiceItem extends Model
{
    use HasFactory;

    protected $table = 'lease_service_items';

    protected $fillable = [
        'lease_id',
        'service_type',
        'quantity',
        'custom_price',
        'effective_date',
        'expiry_date',
    ];

    protected $casts = [
        'service_type' => ServiceType::class,
        'quantity' => 'integer',
        'custom_price' => 'integer',
        'effective_date' => 'date',
        'expiry_date' => 'date',
    ];

    public function lease(): BelongsTo
    {
        return $this->belongsTo(Lease::class);
    }
}