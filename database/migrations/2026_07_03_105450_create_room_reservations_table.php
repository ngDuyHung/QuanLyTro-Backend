<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('room_reservations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('room_id')->constrained('rooms')->restrictOnDelete(); // Khóa ngoại đến bảng rooms, không cho phép xóa nếu có đặt cọc
            $table->foreignId('lease_id')->nullable()->constrained('leases')->nullOnDelete()->comment('FK leases - Nếu đã ký hợp đồng thì trỏ vào đây');
            // Thông tin khách cọc (chưa cần tạo thành user/tenant chính thức)
            $table->string('tenant_name', 100)->comment('Tên khách cọc');
            $table->string('tenant_phone', 15)->comment('Số điện thoại khách cọc');

            $table->bigInteger('deposit_amount')->unsigned()->comment('Số tiền đặt cọc');
            $table->date('expected_move_in_date')->comment('Ngày hẹn dọn vào');

            $table->enum('status', ['pending', 'completed', 'cancelled'])->default('pending')->comment('Trạng thái đặt cọc: pending (chờ xác nhận), completed (đã xác nhận), cancelled (đã hủy)');
            $table->text('note')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('room_reservations');
    }
};
