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
            $table->timestamps();

            // Đảm bảo một hợp đồng không bị trùng lặp loại dịch vụ đăng ký cố định
            $table->unique(['lease_id', 'service_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lease_service_items');
    }
};