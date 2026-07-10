<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // BẢNG 1: THÔNG TIN CHỐT SỔ (MASTER)
        Schema::create('accounting_ledgers', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete()
                  ->comment('Chủ trọ sở hữu sổ kế toán này');

            $table->foreignId('property_id')->nullable()->constrained('properties')->cascadeOnDelete()
                  ->comment('Có thể chốt riêng từng khu, hoặc null nếu chốt gộp toàn hệ thống');

            $table->string('period_type', 20)->default('month')
                  ->comment('Loại kỳ chốt: month, quarter, year');
            $table->unsignedSmallInteger('period_year')
                  ->comment('Năm chốt sổ (VD: 2026)');
            $table->unsignedTinyInteger('period_month')->nullable()
                  ->comment('Tháng chốt sổ (1-12, null nếu chốt theo năm)');

            $table->unsignedBigInteger('total_revenue')->default(0)
                  ->comment('Tổng doanh thu chốt tại thời điểm này');

            $table->text('note')->nullable()
                  ->comment('Ghi chú của chủ trọ cho kỳ này');

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete()
                  ->comment('Người thực hiện thao tác chốt');

            $table->timestamps();

            $table->index(['user_id', 'period_year']);
            $table->index(['property_id']);
        });

        // BẢNG 2: CHI TIẾT SỔ (DETAILS - LINE ITEMS)
        Schema::create('accounting_ledger_details', function (Blueprint $table) {
            $table->id();

            $table->foreignId('accounting_ledger_id')->constrained('accounting_ledgers')->cascadeOnDelete()
                  ->comment('Thuộc về lần chốt sổ nào');

            $table->date('transaction_date')->nullable()
                  ->comment('Ngày, tháng ghi sổ (Ngày khách thanh toán)');
            
            $table->string('transaction_code', 50)->nullable()
                  ->comment('Chứng từ: Số hiệu (Mã phiếu thu)');
            
            $table->text('description')->nullable()
                  ->comment('Diễn giải nội dung thu tiền (VD: Tiền phòng, điện, nước)');
            
            $table->unsignedBigInteger('amount')->default(0)
                  ->comment('Doanh thu của dòng này');

            $table->timestamps();
            
            $table->index(['accounting_ledger_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('accounting_ledger_details');
        Schema::dropIfExists('accounting_ledgers');
    }
};