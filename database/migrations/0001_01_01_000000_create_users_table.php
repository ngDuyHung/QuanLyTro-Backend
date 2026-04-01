<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tạo bảng users (tài khoản hệ thống)
     *
     * Role:
     *   - admin: Quản trị hệ thống (toàn quyền)
     *   - chu_tro: Chủ trọ (quản lý khu nhà, phòng, hóa đơn)
     *   - nguoi_thue: Người thuê (xem phòng, hóa đơn, xác nhận thanh toán)
     *
     * Tạo bảng password_reset_tokens (reset mật khẩu)
     * Tạo bảng sessions (quản lý session người dùng)
     */
    public function up(): void
    {
        // ========== BẢNG USERS ==========
        Schema::create('users', function (Blueprint $table) {
            $table->id()->comment('ID người dùng');
            $table->string('name')->comment('Tên người dùng');
            $table->string('email')->unique()->comment('Email unique');
            $table->string('phone', 15)->nullable()->index()
                ->comment('Số điện thoại, dùng để tìm kiếm');
            $table->enum('role', ['admin', 'chu_tro', 'nguoi_thue'])
                ->default('nguoi_thue')
                ->index()
                ->comment('Vai trò trong hệ thống (admin/chu_tro/nguoi_thue)');
            $table->boolean('is_active')
                ->default(true)
                ->index()
                ->comment('Người dùng đang hoạt động');
            $table->timestamp('email_verified_at')->nullable()
                ->comment('Thời điểm verify email');
            $table->string('password')->comment('Mật khẩu hashed');
            $table->rememberToken()->comment('Token remember-me');
            $table->timestamps();
        });

        // ========== BẢNG PASSWORD RESET TOKENS ==========
        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary()->comment('Email primary key');
            $table->string('token')->comment('Token reset mật khẩu');
            $table->timestamp('created_at')->nullable()
                ->comment('Thời điểm tạo token');
        });

        // ========== BẢNG SESSIONS ==========
        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary()->comment('Session ID');
            $table->foreignId('user_id')->nullable()->index()
                ->comment('FK người dùng, NULL = guest');
            $table->string('ip_address', 45)->nullable()
                ->comment('Địa chỉ IP');
            $table->text('user_agent')->nullable()
                ->comment('User agent (browser, device)');
            $table->longText('payload')->comment('Dữ liệu session');
            $table->integer('last_activity')->index()
                ->comment('Unix timestamp hoạt động lần cuối');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sessions');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('users');
    }
};
