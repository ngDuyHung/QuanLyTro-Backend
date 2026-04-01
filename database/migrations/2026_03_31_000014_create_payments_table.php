<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Bảng payments (thanh toán)
     *
     * Ghi nhận mỗi lần khách thuê/chủ trọ thanh toán.
     * Một hóa đơn có thể được thanh toán nhiều lần (thanh toán một phần).
     */
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id()->comment('ID bản ghi thanh toán');
            $table->unsignedBigInteger('invoice_id')
                ->comment('FK invoices - thanh toán cho hóa đơn nào');
            $table->unsignedBigInteger('bank_account_id')->nullable()
                ->comment('FK bank_accounts - tài khoản nhận tiền (nếu chuyển khoản)');
            $table->unsignedBigInteger('amount')
                ->comment('Số tiền thanh toán (VND)');
            $table->enum('method', ['cash', 'bank_transfer'])
                ->comment('Hình thức thanh toán');
            $table->date('payment_date')
                ->comment('Ngày thanh toán');
            $table->string('note', 255)->nullable()
                ->comment('Ghi chú thanh toán');
            $table->timestamp('created_at')->useCurrent()
                ->comment('Thời điểm tạo bản ghi');

            $table->foreign('invoice_id')
                ->references('id')
                ->on('invoices')
                ->onDelete('cascade');

            $table->foreign('bank_account_id')
                ->references('id')
                ->on('bank_accounts')
                ->onDelete('set null');

            $table->index('invoice_id');
            $table->index('bank_account_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
