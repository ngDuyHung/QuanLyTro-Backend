<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\PropertyType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Property extends Model
{
    use HasFactory;

    protected $table = 'properties';

    protected $fillable = [
        'user_id',
        'property_type',
        'name',
        'address',
        'description',
    ];

    protected $casts = [
        'property_type' => PropertyType::class,
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    // ===== Relationships =====

    /**
     * Khu nhà thuộc về chủ trọ (user role = landlord).
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Khu nhà có nhiều phòng.
     */
    public function rooms(): HasMany
    {
        return $this->hasMany(Room::class);
    }

    /**
     * Giá dịch vụ riêng của khu nhà này (override giá mặc định).
     */
    public function servicePrices(): HasMany
    {
        return $this->hasMany(ServicePrice::class);
    }
}
