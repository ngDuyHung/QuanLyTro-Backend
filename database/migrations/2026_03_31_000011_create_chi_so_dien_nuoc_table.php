<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tạo bảng chi_so_dien_nuoc (chỉ số điện nước)
     *
     * Ghi nhận chỉ số điện/nước định kỳ (thường hàng tháng).
     * Bản ghi đầu tiên (chi_so_cu = 0) chính là chỉ số dầu khi ký hợp đồng.
     *
     * Liên kết với hóa đơn để biết chỉ số nào được tính trong hóa đơn nào.
     */
    public function up(): void
    {
        Schema::create('chi_so_dien_nuoc', function (Blueprint $table) {
            $table->id()->comment('ID bản ghi chỉ số');
            $table->unsignedBigInteger('ban_ghi_thue_id')
                ->comment('FK ban_ghi_thue - chỉ số của phòng nào');
            $table->unsignedBigInteger('hoa_don_id')->nullable()
                ->comment('FK hoa_don - hóa đơn nào sử dụng chỉ số này (nullable = chưa lập hóa đơn)');
            $table->enum('loai', ['dien', 'nuoc'])
                ->comment('Loại: điện hay nước');
            $table->unsignedInteger('chi_so_cu')
                ->default(0)
                ->comment('Chỉ số cũ (bản ghi đầu tiên = 0 = chỉ số ban đầu)');
            $table->unsignedInteger('chi_so_moi')
                ->default(0)
                ->comment('Chỉ số mới');
            $table->string('anh_dong_ho', 500)->nullable()
                ->comment('URL ảnh đồng hồ');
            $table->date('ngay_ghi')
                ->comment('Ngày chốt chỉ số');
            $table->timestamp('created_at')->useCurrent()
                ->comment('Thời điểm ghi nhận');

            // Constraints
            $table->foreign('ban_ghi_thue_id')
                ->references('id')
                ->on('ban_ghi_thue')
                ->onDelete('cascade')
                ->comment('Xóa hợp đồng → xóa tất cả chỉ số');

            $table->foreign('hoa_don_id')
                ->references('id')
                ->on('hoa_don')
                ->onDelete('cascade')
                ->comment('Xóa hóa đơn → xóa chỉ số liên kết (optional)');

            // Indexes
            $table->index('ban_ghi_thue_id');
            $table->index('hoa_don_id');
            $table->index(['loai', 'ngay_ghi']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chi_so_dien_nuoc');
    }
};
