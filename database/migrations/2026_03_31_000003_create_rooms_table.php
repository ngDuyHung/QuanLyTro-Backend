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
     * Trạng thái: available / occupied / maintenance
     */
    public function up(): void
    {
        Schema::create('rooms', function (Blueprint $table) {
            $table->id()->comment('ID phòng');
            $table->unsignedBigInteger('property_id')
                ->comment('FK properties - phòng thuộc khu nào');
            $table->string('name', 50)->comment('Tên phòng (VD: Phòng 01, 02)');
            $table->decimal('area', 6, 2)->nullable()
                ->comment('Diện tích phòng (m²)');
            $table->unsignedTinyInteger('max_occupants')
                ->default(0)
                ->comment('Số người ở tối đa, 0 = không giới hạn');
            $table->unsignedBigInteger('current_price')
                ->default(0)
                ->comment('Giá phòng hiện tại (VND)');
            $table->enum('status', ['available', 'occupied', 'maintenance'])
                ->default('available')
                ->index()
                ->comment('Tình trạng phòng');
            $table->text('description')->nullable()
                ->comment('Mô tả thêm về phòng (tiện nghi, nội thất)');
            $table->timestamps();

            $table->unique(['property_id', 'name'], 'uq_property_room_name');

            $table->foreign('property_id')
                ->references('id')
                ->on('properties')
                ->onDelete('cascade');

            $table->index('property_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rooms');
    }
};
