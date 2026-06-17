<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Tenant extends Model
{
    use HasFactory;

    protected $table = 'tenants';

    protected $fillable = [
        'user_id',
        'full_name',
        'email',
        'phone',
        'id_card_number',
        'id_card_front_image',
        'id_card_back_image',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    // ===== Relationships =====

    /**
     * Khách thuê có thể liên kết với tài khoản đăng nhập hệ thống.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Khách thuê có nhiều hợp đồng thuê (qua nhiều phòng/thời gian).
     */
    public function leases(): HasMany
    {
        return $this->hasMany(Lease::class);
    }

    public function roomResidents(): HasMany
    {
        return $this->hasMany(RoomResident::class);
    }

    public function currentResidence(): HasOne
    {
        return $this->hasOne(RoomResident::class)
            ->whereIn('status', ['pending', 'active'])
            ->latestOfMany();
    }

    public function leaseMembers(): HasMany
    {
        return $this->hasMany(LeaseMember::class);
    }
}
