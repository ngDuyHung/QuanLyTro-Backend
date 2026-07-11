<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Bảng lưu thông tin sự cố
        Schema::create('incidents', function (Blueprint $table) {
            $table->id()->comment('ID sự cố');
            $table->foreignId('property_id')->constrained('properties')->cascadeOnDelete()->comment('FK khu nhà');
            $table->foreignId('room_id')->nullable()->constrained('rooms')->nullOnDelete()->comment('FK phòng (NULL = khu vực chung)');
            $table->foreignId('reported_by_tenant_id')->nullable()->constrained('tenants')->nullOnDelete()->comment('Người thuê báo cáo (NULL = Chủ trọ tự ghi nhận)');

            $table->string('title', 255)->comment('Tiêu đề sự cố ngắn gọn');
            $table->text('description')->nullable()->comment('Mô tả chi tiết tình trạng');

            // Hardcode enum trực tiếp trong DB theo đúng yêu cầu
            $table->enum('category', ['electrical', 'water', 'furniture', 'security', 'other'])->default('other')->comment('Phân loại');
            $table->enum('priority', ['low', 'normal', 'high', 'emergency'])->default('normal')->comment('Mức độ ưu tiên');
            $table->enum('status', ['pending', 'processing', 'resolved', 'cancelled'])->default('pending')->comment('Trạng thái hiện tại');

            $table->bigInteger('repair_cost')->unsigned()->default(0)->comment('Tổng chi phí sửa chữa');
            $table->enum('payer', ['landlord', 'tenant', 'none'])->nullable()->comment('Bên chịu chi phí');

            // Các khóa ngoại liên kết dòng tiền
            $table->foreignId('financial_transaction_id')->nullable()->constrained('financial_transactions')->nullOnDelete()->comment('Link tới phiếu chi (nếu chủ trọ trả tiền)');
            $table->foreignId('invoice_id')->nullable()->constrained('invoices')->nullOnDelete()->comment('Link tới hóa đơn (nếu khách thuê đền)');

            $table->timestamp('resolved_at')->nullable()->comment('Thời gian chốt sự cố');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete()->comment('Tài khoản admin/chủ trọ tạo trên hệ thống');
            $table->timestamps();
        });

        // 2. Bảng lưu hình ảnh sự cố
        Schema::create('incident_images', function (Blueprint $table) {
            $table->id()->comment('ID ảnh');
            $table->foreignId('incident_id')->constrained('incidents')->cascadeOnDelete()->comment('Thuộc sự cố nào');
            $table->string('image_path', 500)->comment('Đường dẫn file ảnh lưu trên storage');
            $table->enum('type', ['before_repair', 'after_repair'])->default('before_repair')->comment('Loại ảnh: Trước khi sửa hay Sau khi sửa');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('incident_images');
        Schema::dropIfExists('incidents');
    }
};
