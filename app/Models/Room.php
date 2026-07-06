<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\RoomStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Room extends Model
{
    use HasFactory;

    protected $table = 'rooms';

    protected $fillable = [
        'property_id',
        'name',
        'area',
        'floor_number',
        'max_occupants',
        'current_price',
        'deposit_amount',
        'billing_day', // Ngày trong tháng để tính tiền phòng, 0 nếu tính theo ngày vào
        'allow_shared', // Cho phép ở ghép
        'is_public', // Có đăng phòng lên trang công khai hay không
        'status',
        'description',
        
    ];

    protected $casts = [
        'area'          => 'decimal:2',
        'floor_number' => 'integer',
        'max_occupants' => 'integer',
        'current_price' => 'integer',
        'deposit_amount' => 'integer',
        'billing_day'   => 'integer',
        'allow_shared'  => 'boolean',
        'is_public'     => 'boolean',
        'status'        => RoomStatus::class,
        'created_at'    => 'datetime',
        'updated_at'    => 'datetime',
    ];

    // ===== Relationships =====


    /**
     * Phòng có nhiều hình ảnh.
     */
    public function images(): HasMany
    {
        return $this->hasMany(RoomImage::class);
    }

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
        $query->where('status', RoomStatus::Available->value);
    }

    public function scopeOccupied(\Illuminate\Database\Eloquent\Builder $query): void
    {
        $query->where('status', RoomStatus::Occupied->value);
    }

    protected static function booted(): void
    {
        static::deleting(function (Room $room): void {
            $room->loadMissing('images');

            foreach ($room->images as $image) {
                if ($image->image_path) {
                    Storage::disk('public')->delete($image->image_path);
                }
            }
        });
    }

    public function residents(): HasMany
    {
        return $this->hasMany(RoomResident::class);
    }

    public function currentResidents(): HasMany
    {
        return $this->hasMany(RoomResident::class)
            ->where('status', 'active');
    }

    /**
     * Phòng có nhiều hóa đơn.
     */
    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    /**
     * CHỈ lấy người đại diện hiện tại của phòng.
     */
    public function representative(): HasOne
    {
        return $this->hasOne(RoomResident::class)
            ->where('role', 'representative')
            ->where('status', 'active') // Đồng nhất với hàm currentResidents của bạn
            ->latestOfMany();
    }

    public function activeLease(): HasOne
    {
        return $this->hasOne(Lease::class)
            ->where('status', 'active')
            ->latestOfMany();
    }

    public function reservations(): HasMany
    {
        return $this->hasMany(RoomReservation::class);
    }
}
