<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Bảng room_price_histories (lịch sử giá phòng)
     *
     * Ghi lại mỗi lần giá phòng thay đổi — AUDIT TRAIL.
     */
    public function up(): void
    {
        Schema::create('room_price_histories', function (Blueprint $table) {
            $table->id()->comment('ID bản ghi lịch sử');
            $table->unsignedBigInteger('room_id')
                ->comment('FK rooms - phòng nào thay đổi giá');
            $table->unsignedBigInteger('user_id')->nullable()
                ->comment('FK users - người thực hiện thay đổi (audit trail)');
            $table->unsignedBigInteger('old_price')
                ->comment('Giá cũ (VND)');
            $table->unsignedBigInteger('new_price')
                ->comment('Giá mới (VND)');
            $table->date('effective_date')
                ->comment('Ngày bắt đầu áp dụng giá mới');
            $table->string('note', 255)->nullable()
                ->comment('Ghi chú lý do thay đổi giá');
            $table->timestamp('created_at')->useCurrent()
                ->comment('Thời điểm ghi nhận');

            $table->foreign('room_id')
                ->references('id')
                ->on('rooms')
                ->onDelete('cascade');

            $table->foreign('user_id')
                ->references('id')
                ->on('users')
                ->onDelete('set null');

            $table->index('room_id');
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('room_price_histories');
    }
};
