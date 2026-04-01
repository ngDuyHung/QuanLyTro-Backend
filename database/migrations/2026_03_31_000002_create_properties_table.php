<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Bảng properties (khu nhà trọ)
     *
     * Mỗi khu nhà được chủ trọ (user role = landlord) quản lý.
     * Một chủ trọ có thể quản lý nhiều khu nhà.
     */
    public function up(): void
    {
        Schema::create('properties', function (Blueprint $table) {
            $table->id()->comment('ID khu nhà');
            $table->unsignedBigInteger('user_id')
                ->comment('FK users - chủ trọ sở hữu khu nhà');
            $table->string('name', 100)->comment('Tên khu nhà trọ');
            $table->string('address', 255)->comment('Địa chỉ chi tiết');
            $table->text('description')->nullable()
                ->comment('Mô tả thêm về khu nhà');
            $table->timestamps();

            $table->foreign('user_id')
                ->references('id')
                ->on('users')
                ->onDelete('restrict');

            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('properties');
    }
};
