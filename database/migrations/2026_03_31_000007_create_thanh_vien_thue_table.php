<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tạo bảng thanh_vien_thue (thành viên thuê / co-tenants / roommates)
     *
     * Lưu thông tin những người khác cùng ở trong 1 phòng
     * (không phải người ký hợp đồng chính).
     *
     * VD: Ông A ký hợp đồng, nhưng con ông A, vợ ông A cũng ở → lưu họ vào bảng này.
     */
    public function up(): void
    {
        Schema::create('thanh_vien_thue', function (Blueprint $table) {
            $table->id()->comment('ID thành viên');
            $table->unsignedBigInteger('ban_ghi_thue_id')
                ->comment('FK ban_ghi_thue - thành viên của hợp đồng nào');
            $table->string('ho_ten', 100)->comment('Họ và tên thành viên');
            $table->year('nam_sinh')->nullable()
                ->comment('Năm sinh (để phân biệt trẻ em/người lớn cho tính phí)');
            $table->enum('quan_he', ['vo_chong', 'con', 'cha_me', 'anh_chi_em', 'ban_be', 'khac'])
                ->default('khac')
                ->comment('Quan hệ với người ký hợp đồng');
            $table->string('so_cccd', 20)->nullable()
                ->comment('Số CCCD/CMND nếu có');
            $table->string('so_dien_thoai', 15)->nullable()
                ->comment('Số điện thoại liên lạc');
            $table->string('ghi_chu', 255)->nullable()
                ->comment('Ghi chú thêm');
            $table->date('ngay_vao')->nullable()
                ->comment('Ngày thành viên này chuyển vào (có thể sau ngày ký HĐ)');
            $table->date('ngay_ra')->nullable()
                ->comment('Ngày rời phòng, NULL = đang ở');
            $table->timestamp('created_at')->useCurrent()
                ->comment('Thời điểm tạo bản ghi');
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate()
                ->comment('Thời điểm cập nhật cuối cùng');

            // Constraints
            $table->foreign('ban_ghi_thue_id')
                ->references('id')
                ->on('ban_ghi_thue')
                ->onDelete('cascade')
                ->comment('Xóa hợp đồng → xóa tất cả thành viên của HĐ đó');

            // Indexes
            $table->index('ban_ghi_thue_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('thanh_vien_thue');
    }
};
