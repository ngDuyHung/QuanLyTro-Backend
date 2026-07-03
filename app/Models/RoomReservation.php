<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RoomReservation extends Model
{
    protected $fillable = [
        'room_id',
        'tenant_name',
        'tenant_phone',
        'deposit_amount',
        'expected_move_in_date',
        'status',
        'note',
    ];

    protected $casts = [
        'expected_move_in_date' => 'date',
    ];

    // một đặt cọc thuộc về một phòng
    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }
}