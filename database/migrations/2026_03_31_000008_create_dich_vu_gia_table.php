<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tạo bảng dich_vu_gia (giá dịch vụ)
     *
     * Quản lý giá cấp nước, điện, rác, internet.
     * Mỗi loại dịch vụ có 1 bản ghi hiện hành.
     *
     * Cấu trúc:
     * - khu_nha_id = NULL → giá mặc định toàn hệ thống
     * - khu_nha_id = <ID> → override giá riêng cho khu đó
     *
     * UNIQUE(khu_nha_id, loai_dich_vu) đảm bảo không có 2 bản ghi cùng loại cho 1 khu.
     */
    public function up(): void
    {
        Schema::create('dich_vu_gia', function (Blueprint $table) {
            $table->id()->comment('ID bản ghi giá dịch vụ');
            $table->unsignedBigInteger('khu_nha_id')->nullable()
                ->comment('FK khu_nha - NULL = giá mặc định, value = giá riêng cho khu');
            $table->enum('loai_dich_vu', ['dien', 'nuoc', 'rac', 'internet'])
                ->comment('Loại dịch vụ');
            $table->unsignedBigInteger('don_gia')
                ->comment('Giá đơn vị (VND/kWh hoặc VND/m³ hoặc cố định/tháng)');
            $table->unsignedInteger('mien_phi_den')
                ->default(0)
                ->comment('Số đơn vị miễn phí (chỉ áp dụng cho điện & nước)');
            $table->enum('kieu_mien_phi', ['khong', 'theo_phong', 'theo_nguoi'])
                ->default('khong')
                ->comment('Kiểu miễn phí: khong / theo_phong (mỗi phòng) / theo_nguoi (mỗi người)');
            $table->date('ngay_hieu_luc')
                ->comment('Ngày bắt đầu áp dụng giá này');
            $table->date('ngay_het_hieu_luc')->nullable()
                ->comment('Ngày hết hiệu lực, NULL = đang áp dụng');
            $table->string('ghi_chu', 255)->nullable()
                ->comment('Ghi chú thêm');
            $table->timestamps();

            // UNIQUE constraint đảm bảo không có 2 bản ghi cùng loại cho 1 khu
            $table->unique(['khu_nha_id', 'loai_dich_vu'], 'uq_dv_khu_loai')
                ->comment('Không được phép 2 bản ghi cùng loại dịch vụ cho 1 khu');

            // Constraints
            $table->foreign('khu_nha_id')
                ->references('id')
                ->on('khu_nha')
                ->onDelete('cascade')
                ->comment('Xóa khu → xóa tất cả giá dịch vụ của khu');

            // Indexes
            $table->index('khu_nha_id');
            $table->index('loai_dich_vu');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dich_vu_gia');
    }
};
