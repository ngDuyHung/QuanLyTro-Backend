<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tạo bảng lich_su_gia_dv (lịch sử giá dịch vụ)
     *
     * Ghi lại mỗi lần giá dịch vụ thay đổi cùng với:
     * - Giá cũ, giá mới
     * - Người thực hiện (user_id) — AUDIT TRAIL
     * - Ngày thay đổi
     * - Lý do thay đổi
     */
    public function up(): void
    {
        Schema::create('lich_su_gia_dv', function (Blueprint $table) {
            $table->id()->comment('ID bản ghi lịch sử giá dịch vụ');
            $table->unsignedBigInteger('dich_vu_id')
                ->comment('FK dich_vu_gia - dịch vụ nào thay đổi giá');
            $table->unsignedBigInteger('user_id')->nullable()
                ->comment('FK users - người thực hiện thay đổi (audit trail)');
            $table->unsignedBigInteger('don_gia_cu')
                ->comment('Giá cũ (VND)');
            $table->unsignedBigInteger('don_gia_moi')
                ->comment('Giá mới (VND)');
            $table->date('ngay_thay_doi')
                ->comment('Ngày thay đổi giá');
            $table->string('ly_do', 255)->nullable()
                ->comment('Lý do thay đổi giá');
            $table->timestamp('created_at')->useCurrent()
                ->comment('Thời điểm ghi nhận');

            // Constraints
            $table->foreign('dich_vu_id')
                ->references('id')
                ->on('dich_vu_gia')
                ->onDelete('cascade')
                ->comment('Xóa dịch vụ → xóa tất cả lịch sử giá của nó');

            $table->foreign('user_id')
                ->references('id')
                ->on('users')
                ->onDelete('set null')
                ->comment('Nếu xóa user → giữ lại bản ghi với user_id = NULL');

            // Indexes
            $table->index('dich_vu_id');
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lich_su_gia_dv');
    }
};
