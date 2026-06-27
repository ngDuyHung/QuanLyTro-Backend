<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\LeaseStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Lease extends Model
{
    use HasFactory;

    protected $table = 'leases';

    protected $fillable = [
        'room_id',
        'tenant_id',
        'start_date',
        'end_date',
        'billing_day',
        'deposit',
        'move_out_notice_date',
        'status',
    ];

    protected $casts = [
        'start_date'           => 'date',
        'end_date'             => 'date',
        'billing_day'          => 'integer',
        'deposit'              => 'integer',
        'move_out_notice_date' => 'date',
        'status'               => LeaseStatus::class,
        'created_at'           => 'datetime',
        'updated_at'           => 'datetime',
    ];

    // ===== Relationships =====

    /**
     * Hợp đồng thuê thuộc về phòng nào.
     */
    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    /**
     * Hợp đồng thuê thuộc về khách thuê nào (người ký hợp đồng).
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Các thành viên cùng ở trong phòng (không phải người ký HĐ).
     */
    public function members(): HasMany
    {
        return $this->hasMany(LeaseMember::class);
    }

    /**
     * Các hóa đơn theo từng kỳ.
     */
    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    /**
     * Các bản ghi chỉ số điện nước.
     */
    public function meterReadings(): HasMany
    {
        return $this->hasMany(MeterReading::class);
    }

    // ===== Scopes =====

    public function scopeActive(\Illuminate\Database\Eloquent\Builder $query): void
    {
        $query->where('status', LeaseStatus::Active);
    }


    public function roomResidents(): HasMany
    {
        return $this->hasMany(RoomResident::class);
    }

    /**
     * Các dịch vụ cố định được đăng ký sử dụng trong hợp đồng này.
     */
    public function serviceItems(): HasMany
    {
        return $this->hasMany(LeaseServiceItem::class, 'lease_id');
    }
}
