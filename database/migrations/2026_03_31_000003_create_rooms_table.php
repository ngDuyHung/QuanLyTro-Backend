<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Bảng rooms (phòng trọ)
     *
     * Mỗi phòng thuộc một khu nhà. UNIQUE(property_id, name)
     * không được tạo 2 phòng cùng tên trong 1 khu.
     *
     * Trạng thái:
     * - available: phòng trống
     * - occupied: đang có hợp đồng thuê còn hiệu lực
     * - maintenance: đang bảo trì, không cho thuê
     */
    public function up(): void
    {
        Schema::create('rooms', function (Blueprint $table) {
            $table->id()->comment('ID phòng');

            $table->unsignedBigInteger('property_id')
                ->comment('FK properties - phòng thuộc khu nào');

            $table->string('name', 50)
                ->comment('Tên phòng (VD: Phòng 01, 101, A104)');

            $table->smallInteger('floor_number')
                ->nullable()
                ->comment('Tầng của phòng: 0 = trệt, 1 = tầng 1, NULL = không xác định');

            $table->decimal('area', 6, 2)
                ->nullable()
                ->comment('Diện tích phòng (m²)');

            $table->unsignedTinyInteger('max_occupants')
                ->default(0)
                ->comment('Số người ở tối đa, 0 = không giới hạn');

            $table->unsignedBigInteger('current_price')
                ->default(0)
                ->comment('Giá phòng hiện tại (VND)');

            $table->unsignedTinyInteger('billing_day')
                ->nullable()
                ->comment('Ngày thu tiền hằng tháng, NULL = theo cấu hình khu nhà');

            $table->boolean('allow_shared')
                ->default(false)
                ->comment('Cho phép ở ghép');

            $table->boolean('is_public')
                ->default(false)
                ->comment('Có đăng phòng lên trang công khai hay không');

            $table->enum('status', ['available', 'occupied', 'maintenance'])
                ->default('available')
                ->comment('Tình trạng phòng');

            $table->text('description')
                ->nullable()
                ->comment('Mô tả thêm về phòng (tiện nghi, nội thất, quy định riêng)');

            $table->timestamps();

            $table->unique(['property_id', 'name'], 'uq_property_room_name');

            $table->foreign('property_id')
                ->references('id')
                ->on('properties')
                ->onDelete('cascade');

            $table->index('property_id');
            $table->index('floor_number');
            $table->index('billing_day');
            $table->index('is_public');
            $table->index('status');
            $table->index(['property_id', 'status'], 'idx_rooms_property_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rooms');
    }
};
