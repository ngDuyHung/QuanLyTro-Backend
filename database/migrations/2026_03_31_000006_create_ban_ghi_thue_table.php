<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tạo bảng ban_ghi_thue (bản ghi thuê / hợp đồng thuê phòng)
     *
     * Ghi lại hợp đồng thuê giữa khách thuê và chủ trọ.
     * Một khách thuê có thể thuê nhiều phòng (hoặc cùng 1 phòng trong các khoảng thời gian khác nhau).
     *
     * Lưu ý: chi_so_dien_dau/nuoc_dau KHÔNG còn ở đây (dự phòng v1),
     * thay vào đó lằm bản ghi đầu tiên trong chi_so_dien_nuoc.
     */
    public function up(): void
    {
        Schema::create('ban_ghi_thue', function (Blueprint $table) {
            $table->id()->comment('ID bản ghi thuê');
            $table->unsignedBigInteger('phong_id')
                ->comment('FK phong - phòng nào được thuê');
            $table->unsignedBigInteger('khach_thue_id')
                ->comment('FK khach_thue - khách thuê ai');
            $table->date('ngay_bat_dau')
                ->comment('Ngày bắt đầu hợp đồng');
            $table->date('ngay_ket_thuc')->nullable()
                ->comment('Ngày kết thúc hợp đồng, NULL = đang còn ở');
            $table->unsignedTinyInteger('ngay_thu_tien')
                ->default(1)
                ->comment('Ngày thu tiền trong tháng (VD: 19)');
            $table->unsignedBigInteger('tien_coc')
                ->default(0)
                ->comment('Tiền cọc (VND)');
            $table->date('ngay_bao_tra')->nullable()
                ->comment('Ngày khách thông báo trả phòng');
            $table->enum('trang_thai', ['dang_thue', 'da_tra'])
                ->default('dang_thue')
                ->index()
                ->comment('Trạng thái hợp đồng');
            $table->timestamps();

            // Constraints
            $table->foreign('phong_id')
                ->references('id')
                ->on('phong')
                ->onDelete('restrict')
                ->comment('Không xóa phòng nếu vẫn có hợp đồng');

            $table->foreign('khach_thue_id')
                ->references('id')
                ->on('khach_thue')
                ->onDelete('restrict')
                ->comment('Không xóa khách nếu vẫn có hợp đồng');

            // Indexes
            $table->index('phong_id');
            $table->index('khach_thue_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ban_ghi_thue');
    }
};
