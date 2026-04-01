<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tạo bảng thanh_toan (thanh toán)
     *
     * Ghi nhận mỗi lần khách thuê/chủ trọ thanh toán.
     * Một hóa đơn có thể được thanh toán nhiều lần (thanh toán một phần).
     *
     * Hình thức thanh toán:
     * - tien_mat: Tiền mặt
     * - chuyen_khoan: Chuyển khoản (liên kết tài_khoan_ngan_hang)
     */
    public function up(): void
    {
        Schema::create('thanh_toan', function (Blueprint $table) {
            $table->id()->comment('ID bản ghi thanh toán');
            $table->unsignedBigInteger('hoa_don_id')
                ->comment('FK hoa_don - thanh toán cho hóa đơn nào');
            $table->unsignedBigInteger('tai_khoan_id')->nullable()
                ->comment('FK tai_khoan_ngan_hang - tài khoản nhận tiền (nếu chuyển khoản)');
            $table->unsignedBigInteger('so_tien')
                ->comment('Số tiền thanh toán (VND)');
            $table->enum('hinh_thuc', ['tien_mat', 'chuyen_khoan'])
                ->comment('Hình thức thanh toán');
            $table->date('ngay_thanh_toan')
                ->comment('Ngày thanh toán');
            $table->string('ghi_chu', 255)->nullable()
                ->comment('Ghi chú thanh toán');
            $table->timestamp('created_at')->useCurrent()
                ->comment('Thời điểm tạo bản ghi');

            // Constraints
            $table->foreign('hoa_don_id')
                ->references('id')
                ->on('hoa_don')
                ->onDelete('cascade')
                ->comment('Xóa hóa đơn → xóa tất cả thanh toán của HĐ');

            $table->foreign('tai_khoan_id')
                ->references('id')
                ->on('tai_khoan_ngan_hang')
                ->onDelete('set null')
                ->comment('Nếu xóa tài khoản → giữ lại thanh toán với tai_khoan_id = NULL');

            // Indexes
            $table->index('hoa_don_id');
            $table->index('tai_khoan_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('thanh_toan');
    }
};
