<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Bảng leases (bản ghi thuê / hợp đồng thuê phòng)
     *
     * Ghi lại hợp đồng thuê giữa khách thuê và chủ trọ.
     */
    public function up(): void
    {
        Schema::create('leases', function (Blueprint $table) {
            $table->id()->comment('ID bản ghi thuê');
            $table->unsignedBigInteger('room_id')
                ->comment('FK rooms - phòng nào được thuê');
            $table->unsignedBigInteger('tenant_id')
                ->comment('FK tenants - khách thuê ai');
            $table->date('start_date')
                ->comment('Ngày bắt đầu hợp đồng');
            $table->date('end_date')->nullable()
                ->comment('Ngày kết thúc hợp đồng, NULL = đang còn ở');
            $table->unsignedTinyInteger('billing_day')
                ->default(1)
                ->comment('Ngày thu tiền trong tháng (VD: 19)');
            $table->unsignedBigInteger('deposit')
                ->default(0)
                ->comment('Tiền cọc (VND)');
            $table->date('move_out_notice_date')->nullable()
                ->comment('Ngày khách thông báo trả phòng');
            $table->enum('status', ['active', 'ended'])
                ->default('active')
                ->index()
                ->comment('Trạng thái hợp đồng');
            $table->timestamps();

            $table->foreign('room_id')
                ->references('id')
                ->on('rooms')
                ->onDelete('restrict');

            $table->foreign('tenant_id')
                ->references('id')
                ->on('tenants')
                ->onDelete('restrict');

            $table->index('room_id');
            $table->index('tenant_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leases');
    }
};
