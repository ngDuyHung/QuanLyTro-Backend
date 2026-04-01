<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tạo bảng khach_thue (khách thuê)
     *
     * Lưu thông tin khách thuê. Mỗi khách thuê có thể:
     * - Liên kết với tài khoản hệ thống (user_id) để đăng nhập xem info
     * - Có email để nhận thông báo, hóa đơn điện tử
     */
    public function up(): void
    {
        Schema::create('khach_thue', function (Blueprint $table) {
            $table->id()->comment('ID khách thuê');
            $table->unsignedBigInteger('user_id')->nullable()
                ->comment('FK users - liên kết tài khoản đăng nhập (nếu có)');
            $table->string('ho_ten', 100)->comment('Họ và tên khách thuê');
            $table->string('email', 255)->nullable()
                ->index()
                ->comment('Email để gửi thông báo, hóa đơn điện tử');
            $table->string('so_dien_thoai', 15)
                ->index()
                ->comment('Số điện thoại');
            $table->string('so_cccd', 20)->unique()
                ->comment('Số CCCD/CMND - không được trùng');
            $table->string('anh_cccd_truoc', 500)->nullable()
                ->comment('URL ảnh mặt trước CCCD');
            $table->string('anh_cccd_sau', 500)->nullable()
                ->comment('URL ảnh mặt sau CCCD');
            $table->timestamps();

            // Constraints
            $table->foreign('user_id')
                ->references('id')
                ->on('users')
                ->onDelete('set null')
                ->comment('Nếu xóa user → khách thuê vẫn tồn tại');

            // Indexes
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('khach_thue');
    }
};
