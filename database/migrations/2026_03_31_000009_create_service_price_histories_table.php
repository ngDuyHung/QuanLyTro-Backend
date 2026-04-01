<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Bảng service_price_histories (lịch sử giá dịch vụ)
     *
     * Ghi lại mỗi lần giá dịch vụ thay đổi — AUDIT TRAIL.
     */
    public function up(): void
    {
        Schema::create('service_price_histories', function (Blueprint $table) {
            $table->id()->comment('ID bản ghi lịch sử');
            $table->unsignedBigInteger('service_price_id')
                ->comment('FK service_prices - dịch vụ nào thay đổi giá');
            $table->unsignedBigInteger('user_id')->nullable()
                ->comment('FK users - người thực hiện thay đổi (audit trail)');
            $table->unsignedBigInteger('old_price')
                ->comment('Giá cũ (VND)');
            $table->unsignedBigInteger('new_price')
                ->comment('Giá mới (VND)');
            $table->date('changed_date')
                ->comment('Ngày thay đổi giá');
            $table->string('reason', 255)->nullable()
                ->comment('Lý do thay đổi giá');
            $table->timestamp('created_at')->useCurrent()
                ->comment('Thời điểm ghi nhận');

            $table->foreign('service_price_id')
                ->references('id')
                ->on('service_prices')
                ->onDelete('cascade');

            $table->foreign('user_id')
                ->references('id')
                ->on('users')
                ->onDelete('set null');

            $table->index('service_price_id');
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_price_histories');
    }
};
