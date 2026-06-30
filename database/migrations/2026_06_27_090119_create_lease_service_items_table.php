<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lease_service_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lease_id')->constrained('leases')->cascadeOnDelete();
            $table->string('service_type')->comment('Loại dịch vụ từ ServiceType Enum');
            $table->integer('quantity')->default(1)->comment('Số lượng đăng ký');
            $table->bigInteger('custom_price')->nullable()->comment('Giá thỏa thuận riêng, nếu null sẽ lấy theo bảng giá Khu nhà/Hệ thống');
            $table->date('effective_date')->comment('Ngày bắt đầu áp dụng mức giá này');
            $table->date('expiry_date')->nullable()->comment('Ngày kết thúc áp dụng, null = đang sử dụng');

            $table->timestamps();

            // Đánh index để truy vấn nhanh thay vì unique constraint
            $table->index(['lease_id', 'service_type', 'effective_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lease_service_items');
    }
};
