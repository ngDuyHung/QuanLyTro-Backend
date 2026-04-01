<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tạo bảng hoa_don (hóa đơn)
     *
     * Hóa đơn được tạo cho mỗi bản ghi thuê và mỗi kỳ thanh toán.
     * VD: Khách A thuê phòng 01, mỗi tháng lập 1 hóa đơn.
     *
     * Trạng thái: chua_thanh_toan / thanh_toan_mot_phan / da_thanh_toan
     */
    public function up(): void
    {
        Schema::create('hoa_don', function (Blueprint $table) {
            $table->id()->comment('ID hóa đơn');
            $table->unsignedBigInteger('ban_ghi_thue_id')
                ->comment('FK ban_ghi_thue - hóa đơn cho hợp đồng nào');
            $table->string('ma_hoa_don', 30)->unique()
                ->comment('Mã hóa đơn (VD: HD-2-202603-0964)');
            $table->date('ky_tu')
                ->comment('Đầu kỳ thanh toán (VD: 2024-02-19)');
            $table->date('ky_den')
                ->comment('Cuối kỳ thanh toán (VD: 2024-03-19)');
            $table->enum('trang_thai', ['chua_thanh_toan', 'da_thanh_toan', 'thanh_toan_mot_phan'])
                ->default('chua_thanh_toan')
                ->index()
                ->comment('Trạng thái thanh toán');
            $table->timestamps();

            // Constraints
            $table->foreign('ban_ghi_thue_id')
                ->references('id')
                ->on('ban_ghi_thue')
                ->onDelete('restrict')
                ->comment('Không xóa bản ghi thuê nếu vẫn có hóa đơn');

            // Indexes
            $table->index('ban_ghi_thue_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hoa_don');
    }
};
