<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Bảng tenants (khách thuê)
     *
     * Lưu thông tin khách thuê. Có thể liên kết tài khoản hệ thống (user_id).
     */
    public function up(): void
    {
        Schema::create('tenants', function (Blueprint $table) {
            $table->id()->comment('ID khách thuê');
            $table->unsignedBigInteger('owner_id')
                ->comment('FK users - Chủ trọ sở hữu hồ sơ này');
            $table->unsignedBigInteger('user_id')->nullable()
                ->comment('FK users - liên kết tài khoản đăng nhập (nếu có)');
            $table->string('full_name', 100)->comment('Họ và tên khách thuê');
            $table->string('email', 255)->nullable()
                ->index()
                ->comment('Email để gửi thông báo, hóa đơn điện tử');
            $table->string('phone', 15)
                ->index()
                ->comment('Số điện thoại');
            $table->string('id_card_number', 20)->nullable()->unique()
                ->comment('Số CCCD/CMND - không được trùng (có thể để trống)');
            $table->string('id_card_front_image', 500)->nullable()
                ->comment('URL ảnh mặt trước CCCD');
            $table->string('id_card_back_image', 500)->nullable()
                ->comment('URL ảnh mặt sau CCCD');
            $table->timestamps();

            // Unique kép: Trong 1 chủ trọ thì SĐT và CCCD không được trùng
            $table->unique(['owner_id', 'phone'], 'uq_owner_phone');
            $table->unique(['owner_id', 'id_card_number'], 'uq_owner_id_card');

            $table->foreign('owner_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenants');
    }
};
