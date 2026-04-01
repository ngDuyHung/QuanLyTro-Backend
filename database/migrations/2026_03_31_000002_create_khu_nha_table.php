<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tạo bảng khu_nha (khu nhà trọ)
     *
     * Mỗi khu nhà được chủ trọ (user role = chu_tro) quản lý.
     * Một chủ trọ có thể quản lý nhiều khu nhà.
     */
    public function up(): void
    {
        Schema::create('khu_nha', function (Blueprint $table) {
            $table->id()->comment('ID khu nhà');
            $table->unsignedBigInteger('user_id')
                ->comment('FK users - chủ trọ sở hữu khu nhà');
            $table->string('ten_khu', 100)->comment('Tên khu nhà trọ');
            $table->string('dia_chi', 255)->comment('Địa chỉ chi tiết');
            $table->text('mo_ta')->nullable()
                ->comment('Mô tả thêm về khu nhà');
            $table->timestamps();

            // Constraints
            $table->foreign('user_id')
                ->references('id')
                ->on('users')
                ->onDelete('restrict')
                ->comment('Chủ trọ không thể xóa nếu vẫn quản lý khu');

            // Indexes
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('khu_nha');
    }
};
