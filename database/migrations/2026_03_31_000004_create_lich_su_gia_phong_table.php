<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tạo bảng lich_su_gia_phong (lịch sử giá phòng)
     *
     * Ghi lại mỗi lần giá phòng thay đổi cùng với:
     * - Giá cũ, giá mới
     * - Người thực hiện (user_id) — AUDIT TRAIL
     * - Ngày có hiệu lực
     * - Ghi chú (lý do thay đổi)
     */
    public function up(): void
    {
        Schema::create('lich_su_gia_phong', function (Blueprint $table) {
            $table->id()->comment('ID bản ghi lịch sử');
            $table->unsignedBigInteger('phong_id')
                ->comment('FK phong - phòng nào thay đổi giá');
            $table->unsignedBigInteger('user_id')->nullable()
                ->comment('FK users - người thực hiện thay đổi (audit trail)');
            $table->unsignedBigInteger('gia_cu')
                ->comment('Giá cũ (VND)');
            $table->unsignedBigInteger('gia_moi')
                ->comment('Giá mới (VND)');
            $table->date('ngay_hieu_luc')
                ->comment('Ngày bắt đầu áp dụng giá mới');
            $table->string('ghi_chu', 255)->nullable()
                ->comment('Ghi chú lý do thay đổi giá');
            $table->timestamp('created_at')->useCurrent()
                ->comment('Thời điểm ghi nhận');

            // Constraints
            $table->foreign('phong_id')
                ->references('id')
                ->on('phong')
                ->onDelete('cascade')
                ->comment('Xóa phòng → xóa tất cả lịch sử giá');

            $table->foreign('user_id')
                ->references('id')
                ->on('users')
                ->onDelete('set null')
                ->comment('Nếu xóa user → giữ lại bản ghi với user_id = NULL');

            // Indexes
            $table->index('phong_id');
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lich_su_gia_phong');
    }
};
