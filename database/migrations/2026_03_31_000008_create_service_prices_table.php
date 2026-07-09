<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Bảng service_prices (giá dịch vụ)
     *
     * Quản lý giá điện, nước, rác, internet.
     * - property_id = NULL → giá mặc định toàn hệ thống
     * - property_id = <ID> → override giá riêng cho khu đó
     */
    public function up(): void
    {
        Schema::create('service_prices', function (Blueprint $table) {
            $table->id()->comment('ID bản ghi giá dịch vụ');
            $table->unsignedBigInteger('property_id')->nullable()
                ->comment('FK properties - NULL = giá mặc định, value = giá riêng cho khu');
            $table->enum('service_type', ['electricity', 'water', 'garbage', 'internet','garbage','parking','cleaning','elevator','management','other'])
                ->comment('Loại dịch vụ');
            $table->unsignedBigInteger('unit_price')
                ->comment('Giá đơn vị (VND/kWh hoặc VND/m³ hoặc cố định/tháng)');
            $table->unsignedInteger('free_units')
                ->default(0)
                ->comment('Số đơn vị miễn phí (chỉ áp dụng cho điện & nước)');
            $table->enum('free_unit_type', ['none', 'per_room', 'per_person'])
                ->default('none')
                ->comment('Kiểu miễn phí: none / per_room / per_person');
            $table->date('effective_date')
                ->comment('Ngày bắt đầu áp dụng giá này');
            $table->date('expiry_date')->nullable()
                ->comment('Ngày hết hiệu lực, NULL = đang áp dụng');
            $table->string('note', 255)->nullable()
                ->comment('Ghi chú thêm');
            $table->timestamps();

            $table->unique(['property_id', 'service_type'], 'uq_property_service_type');

            $table->foreign('property_id')
                ->references('id')
                ->on('properties')
                ->onDelete('cascade');

            $table->index('property_id');
            $table->index('service_type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_prices');
    }
};
