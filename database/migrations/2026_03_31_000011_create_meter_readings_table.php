<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Bảng meter_readings (chỉ số điện nước)
     *
     * Ghi nhận chỉ số điện/nước định kỳ (thường hàng tháng).
     * Bản ghi đầu tiên (previous_reading = 0) là chỉ số đầu khi ký hợp đồng.
     */
    public function up(): void
    {
        Schema::create('meter_readings', function (Blueprint $table) {
            $table->id()->comment('ID bản ghi chỉ số');
            $table->unsignedBigInteger('lease_id')
                ->comment('FK leases - chỉ số của hợp đồng nào');
            $table->unsignedBigInteger('invoice_id')->nullable()
                ->comment('FK invoices - hóa đơn nào sử dụng chỉ số này');
            $table->enum('type', ['electricity', 'water'])
                ->comment('Loại: điện hay nước');
            $table->unsignedInteger('previous_reading')
                ->default(0)
                ->comment('Chỉ số cũ (bản ghi đầu tiên = 0)');
            $table->unsignedInteger('current_reading')
                ->default(0)
                ->comment('Chỉ số mới');
            $table->string('meter_image', 500)->nullable()
                ->comment('URL ảnh đồng hồ');
            $table->text('note')->nullable()
                ->comment('Ghi chú về chỉ số');
            $table->date('reading_date')
                ->comment('Ngày chốt chỉ số');
            $table->timestamp('created_at')->useCurrent()
                ->comment('Thời điểm ghi nhận');

            $table->foreign('lease_id')
                ->references('id')
                ->on('leases')
                ->onDelete('cascade');

            $table->foreign('invoice_id')
                ->references('id')
                ->on('invoices')
                ->onDelete('cascade');

            $table->index('lease_id');
            $table->index('invoice_id');
            $table->index(['type', 'reading_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('meter_readings');
    }
};
