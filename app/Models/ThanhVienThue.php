<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\QuanHeThanhVien;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ThanhVienThue extends Model
{
    use HasFactory;

    protected $table = 'thanh_vien_thue';

    protected $fillable = [
        'ban_ghi_thue_id',
        'ho_ten',
        'so_cccd',
        'nam_sinh',
        'quan_he',
        'so_dien_thoai',
        'ngay_vao',
        'ngay_ra',
        'ghi_chu',
    ];

    protected $casts = [
        'quan_he'  => QuanHeThanhVien::class,
        'nam_sinh' => 'integer',
        'ngay_vao' => 'date',
        'ngay_ra'  => 'date',
    ];

    // ===== Relationships =====

    /**
     * Thành viên thuộc bản ghi thuê nào.
     */
    public function banGhiThue(): BelongsTo
    {
        return $this->belongsTo(BanGhiThue::class);
    }
}
