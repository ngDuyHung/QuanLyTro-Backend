<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class KhachThue extends Model
{
    use HasFactory;

    protected $table = 'khach_thue';

    protected $fillable = [
        'user_id',
        'ho_ten',
        'email',
        'so_dien_thoai',
        'so_cccd',
        'anh_cccd_truoc',
        'anh_cccd_sau',
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
     * Khách thuê có nhiều bản ghi thuê (qua nhiều phòng/thời gian).
     */
    public function banGhiThue(): HasMany
    {
        return $this->hasMany(BanGhiThue::class);
    }
}
