<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Notification extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'title',
        'content',
        'type',
        'target_type',
        'target_id',
        'is_pinned',
        'status',
    ];

    protected $casts = [
        'is_pinned' => 'boolean', // Chỉ cần ép kiểu boolean cho cờ ghim
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    //Liên kết đến Khu nhà
    public function targetProperty(): BelongsTo
    {
        return $this->belongsTo(Property::class, 'target_id');
    }

    // Liên kết đến Phòng
    public function targetRoom(): BelongsTo
    {
        return $this->belongsTo(Room::class, 'target_id');
    }
}
