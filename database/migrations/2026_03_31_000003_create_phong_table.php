<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tạo bảng phong (phòng trọ)
     *
     * Mỗi phòng thuộc một khu nhà. Cần UNIQUE(khu_nha_id, ten_phong)
     * không được tạo 2 phòng cùng tên trong 1 khu.
     *
     * Trạng thái: trong / dang_thue / sua_chua
     */
    public function up(): void
    {
        Schema::create('phong', function (Blueprint $table) {
            $table->id()->comment('ID phòng');
            $table->unsignedBigInteger('khu_nha_id')
                ->comment('FK khu_nha - phòng thuộc khu nào');
            $table->string('ten_phong', 50)->comment('Tên phòng (VD: Phòng 01, 02)');
            $table->decimal('dien_tich', 6, 2)->nullable()
                ->comment('Diện tích phòng (m²)');
            $table->unsignedTinyInteger('so_nguoi_toi_da')
                ->default(0)
                ->comment('Số người ở tối đa, 0 = không giới hạn');
            $table->unsignedBigInteger('gia_hien_tai')
                ->default(0)
                ->comment('Giá phòng hiện tại (VND)');
            $table->enum('trang_thai', ['trong', 'dang_thue', 'sua_chua'])
                ->default('trong')
                ->index()
                ->comment('Tình trạng phòng');
            $table->text('mo_ta')->nullable()
                ->comment('Mô tả thêm về phòng (tiện nghi, nội thất)');
            $table->timestamps();

            // UNIQUE constraint: không được phép tạo 2 phòng cùng tên trong 1 khu
            $table->unique(['khu_nha_id', 'ten_phong'], 'uq_khu_ten')
                ->comment('Không được phép 2 phòng cùng tên trong 1 khu');

            // Constraints
            $table->foreign('khu_nha_id')
                ->references('id')
                ->on('khu_nha')
                ->onDelete('cascade')
                ->comment('Xóa khu → xóa tất cả phòng');

            // Indexes
            $table->index('khu_nha_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('phong');
    }
};
