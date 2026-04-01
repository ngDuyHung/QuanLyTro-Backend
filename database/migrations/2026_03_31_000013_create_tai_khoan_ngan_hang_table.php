<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tạo bảng tai_khoan_ngan_hang (tài khoản ngân hàng)
     *
     * Lưu thông tin tài khoản ngân hàng của chủ trọ để nhận tiền từ khách thuê.
     * Mỗi chủ trọ có thể có nhiều tài khoản ngân hàng.
     */
    public function up(): void
    {
        Schema::create('tai_khoan_ngan_hang', function (Blueprint $table) {
            $table->id()->comment('ID tài khoản');
            $table->unsignedBigInteger('user_id')
                ->comment('FK users - tài khoản của chủ trọ nào');
            $table->string('ten_tai_khoan', 100)
                ->comment('Tên chủ tài khoản');
            $table->string('so_tai_khoan', 30)->unique()
                ->comment('Số tài khoản (unique)');
            $table->string('ngan_hang', 100)
                ->comment('Tên ngân hàng (VD: Vietcombank, MB Bank)');
            $table->string('ma_ngan_hang', 20)->nullable()
                ->comment('BIN hoặc short_name (VCB, TCB, 970415)');
            $table->string('chi_nhanh', 100)->nullable()
                ->comment('Tên chi nhánh');
            $table->boolean('is_default')
                ->default(true)
                ->comment('Tài khoản mặc định để nhận tiền');
            $table->timestamps();

            // Constraints
            $table->foreign('user_id')
                ->references('id')
                ->on('users')
                ->onDelete('cascade')
                ->comment('Xóa chủ trọ → xóa tất cả tài khoản của chủ');

            // Indexes
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tai_khoan_ngan_hang');
    }
};
