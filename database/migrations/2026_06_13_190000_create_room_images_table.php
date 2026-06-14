<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Bảng room_images (ảnh phòng)
     *
     * Một phòng có thể có nhiều ảnh. Ảnh thật lưu ở storage/cloud,
     * bảng này chỉ lưu đường dẫn và thông tin hiển thị.
     */
    public function up(): void
    {
        Schema::create('room_images', function (Blueprint $table) {
            $table->id()->comment('ID ảnh phòng');

            $table->unsignedBigInteger('room_id')
                ->comment('FK rooms - ảnh thuộc phòng nào');

            $table->string('image_path', 255)
                ->comment('Đường dẫn ảnh phòng');

            $table->boolean('is_cover')
                ->default(false)
                ->comment('Ảnh đại diện phòng');

            $table->unsignedSmallInteger('sort_order')
                ->default(0)
                ->comment('Thứ tự hiển thị ảnh');

            $table->timestamps();

            $table->foreign('room_id')
                ->references('id')
                ->on('rooms')
                ->onDelete('cascade');

            $table->index('room_id');
            $table->index('is_cover');
            $table->index(['room_id', 'sort_order'], 'idx_room_images_room_sort');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('room_images');
    }
};
