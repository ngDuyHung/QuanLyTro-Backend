<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Bảng invoice_items (chi tiết hóa đơn / line items)
     *
     * Mỗi hóa đơn có nhiều dòng: tiền phòng, điện, nước, rác, internet...
     * Lưu giá tại thời điểm lập hóa đơn (unit_price_snapshot) để tracking lịch sử.
     */
    public function up(): void
    {
        Schema::create('invoice_items', function (Blueprint $table) {
            $table->id()->comment('ID dòng chi tiết');
            $table->unsignedBigInteger('invoice_id')
                ->comment('FK invoices - dòng này thuộc hóa đơn nào');
            $table->unsignedBigInteger('service_price_id')->nullable()
                ->comment('FK service_prices - dịch vụ được áp dụng');
            $table->enum('charge_type', ['room', 'electricity', 'water', 'garbage', 'internet'])
                ->comment('Loại khoản phí');
            $table->string('description', 255)
                ->comment('Mô tả dòng (VD: "Điện 15kWh x 3.500đ")');
            $table->unsignedBigInteger('unit_price_snapshot')
                ->comment('Giá đơn vị TẠI THỜI ĐIỂM LẬP HÓA ĐƠN (snapshot)');
            $table->decimal('quantity', 10, 2)
                ->default(1.00)
                ->comment('Số lượng (kWh, m³, hoặc số tháng)');
            $table->unsignedBigInteger('total')
                ->default(0)
                ->comment('Tổng tiền dòng = unit_price_snapshot × quantity');
            $table->timestamp('created_at')->useCurrent()
                ->comment('Thời điểm tạo');

            $table->foreign('invoice_id')
                ->references('id')
                ->on('invoices')
                ->onDelete('cascade');

            $table->foreign('service_price_id')
                ->references('id')
                ->on('service_prices')
                ->onDelete('set null');

            $table->index('invoice_id');
            $table->index('service_price_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_items');
    }
};
