<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tạo bảng chi_tiet_hoa_don (chi tiết hóa đơn / line items)
     *
     * Mỗi hóa đơn có nhiều dòng:
     * - 1 dòng tiền phòng
     * - 1 dòng điện (nếu có sử dụng)
     * - 1 dòng nước (nếu có sử dụng)
     * - 1 dòng phí rác
     * - 1 dòng phí internet
     * - ...
     *
     * Lưu giá tại thời điểm lập hóa đơn (don_gia_snapshot) để tracking lịch sử giá.
     */
    public function up(): void
    {
        Schema::create('chi_tiet_hoa_don', function (Blueprint $table) {
            $table->id()->comment('ID dòng chi tiết');
            $table->unsignedBigInteger('hoa_don_id')
                ->comment('FK hoa_don - dòng này thuộc hóa đơn nào');
            $table->unsignedBigInteger('dich_vu_id')->nullable()
                ->comment('FK dich_vu_gia - dịch vụ được áp dụng (nullable = không dịch vụ)');
            $table->enum('loai_phi', ['phong', 'dien', 'nuoc', 'rac', 'internet'])
                ->comment('Loại khoản phí');
            $table->string('mo_ta', 255)
                ->comment('Mô tả dòng (VD: "Điện 15kWh x 3.500đ")');
            $table->unsignedBigInteger('don_gia_snapshot')
                ->comment('Giá đơn vị TẠI THỜI ĐIỂM LẬP HÓA ĐƠN (snapshot)');
            $table->decimal('so_luong', 10, 2)
                ->default(1.00)
                ->comment('Số lượng (kWh, m³, hoặc số tháng)');
            $table->unsignedBigInteger('tong_tien')
                ->default(0)
                ->comment('Cache tổng tiền dòng = don_gia_snapshot × so_luong');
            $table->timestamp('created_at')->useCurrent()
                ->comment('Thời điểm tạo');

            // Constraints
            $table->foreign('hoa_don_id')
                ->references('id')
                ->on('hoa_don')
                ->onDelete('cascade')
                ->comment('Xóa hóa đơn → xóa tất cả chi tiết');

            $table->foreign('dich_vu_id')
                ->references('id')
                ->on('dich_vu_gia')
                ->onDelete('set null')
                ->comment('Nếu xóa dịch vụ → giữ lại dòng với dich_vu_id = NULL');

            // Indexes
            $table->index('hoa_don_id');
            $table->index('dich_vu_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chi_tiet_hoa_don');
    }
};
