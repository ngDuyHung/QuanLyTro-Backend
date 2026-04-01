<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\RoomStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Room extends Model
{
    use HasFactory;

    protected $table = 'rooms';

    protected $fillable = [
        'property_id',
        'name',
        'area',
        'max_occupants',
        'current_price',
        'status',
        'description',
    ];

    protected $casts = [
        'area'          => 'decimal:2',
        'max_occupants' => 'integer',
        'current_price' => 'integer',
        'status'        => RoomStatus::class,
        'created_at'    => 'datetime',
        'updated_at'    => 'datetime',
    ];

    // ===== Relationships =====

    /**
     * Phòng thuộc về một khu nhà.
     */
    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    /**
     * Phòng có nhiều hợp đồng thuê qua các thời kỳ.
     */
    public function leases(): HasMany
    {
        return $this->hasMany(Lease::class);
    }

    /**
     * Lịch sử thay đổi giá phòng.
     */
    public function priceHistories(): HasMany
    {
        return $this->hasMany(RoomPriceHistory::class);
    }

    // ===== Scopes =====

    public function scopeAvailable(\Illuminate\Database\Eloquent\Builder $query): void
    {
        $query->where('status', RoomStatus::Available);
    }

    public function scopeOccupied(\Illuminate\Database\Eloquent\Builder $query): void
    {
        $query->where('status', RoomStatus::Occupied);
    }
}
