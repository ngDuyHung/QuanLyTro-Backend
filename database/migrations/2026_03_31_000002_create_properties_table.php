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

            $table->string('property_type', 50)
                ->default('boarding_house')
                ->comment('Loại khu nhà: boarding_house, apartment,house...');

            $table->string('name', 150)
                ->comment('Tên khu nhà trọ');

            $table->string('code', 50)
                ->comment('Mã khu nhà, ví dụ: KHU-A, KHU-E');

            $table->string('status', 30)
                ->default('active')
                ->comment('Trạng thái: active, inactive');

            $table->unsignedSmallInteger('floors_count')
                ->default(0)
                ->comment('Số tầng của khu nhà, 0 nếu không phân tầng');

            $table->unsignedSmallInteger('expected_rooms_count')
                ->default(0)
                ->comment('Số phòng dự kiến trong khu nhà');

            $table->string('manager_name', 100)
                ->nullable()
                ->comment('Người quản lý hoặc người liên hệ chính');

            $table->string('tax_code', 50)
                ->nullable()
                ->comment('Mã số thuế Hộ kinh doanh');

            $table->string('representative_name', 100)
                ->nullable()
                ->comment('Người đại diện pháp luật (Chủ hộ kinh doanh)');

            $table->string('address', 255)
                ->comment('Địa chỉ chi tiết khu nhà');

            $table->decimal('latitude', 10, 7)
                ->nullable()
                ->comment('Vĩ độ Google Map');

            $table->decimal('longitude', 10, 7)
                ->nullable()
                ->comment('Kinh độ Google Map');

            $table->string('cover_image_path', 255)
                ->nullable()
                ->comment('Đường dẫn ảnh đại diện khu nhà');

            $table->text('description')
                ->nullable()
                ->comment('Ghi chú hoặc mô tả thêm về khu nhà');

            $table->timestamps();

            $table->foreign('user_id')
                ->references('id')
                ->on('users')
                ->onDelete('restrict');

            $table->unique(['user_id', 'code'], 'properties_user_code_unique');

            $table->index('user_id');
            $table->index('status');
            $table->index('property_type');
            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('properties');
    }
};
