<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Bảng invoices (hóa đơn)
     *
     * Hóa đơn được tạo cho mỗi hợp đồng thuê và mỗi kỳ thanh toán.
     * Trạng thái: unpaid / partially_paid / paid
     */
    public function up(): void
    {
        Schema::create('invoices', function (Blueprint $table) {
            $table->id()->comment('ID hóa đơn');
            $table->unsignedBigInteger('lease_id')
                ->comment('FK leases - hóa đơn cho hợp đồng nào');
            $table->string('invoice_code', 30)->unique()
                ->comment('Mã hóa đơn (VD: HD-2-202603-0964)');
            $table->date('period_from')
                ->comment('Đầu kỳ thanh toán');
            $table->date('period_to')
                ->comment('Cuối kỳ thanh toán');
            $table->decimal('total_amount', 15, 2)
                ->comment('Tổng số tiền phải trả');
            $table->enum('status', ['unpaid', 'paid', 'partially_paid'])
                ->default('unpaid')
                ->index()
                ->comment('Trạng thái thanh toán');
            $table->timestamps();

            $table->foreign('lease_id')
                ->references('id')
                ->on('leases')
                ->onDelete('restrict');

            $table->index('lease_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};
