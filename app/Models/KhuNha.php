<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class KhuNha extends Model
{
    use HasFactory;

    protected $table = 'khu_nha';

    protected $fillable = [
        'user_id',
        'ten_khu',
        'dia_chi',
        'mo_ta',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    // ===== Relationships =====

    /**
     * Khu nhà thuộc về chủ trọ (user role = chu_tro).
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Khu nhà có nhiều phòng.
     */
    public function phong(): HasMany
    {
        return $this->hasMany(Phong::class);
    }

    /**
     * Giá dịch vụ riêng của khu nhà này (override giá mặc định).
     */
    public function dichVuGia(): HasMany
    {
        return $this->hasMany(DichVuGia::class);
    }
}
