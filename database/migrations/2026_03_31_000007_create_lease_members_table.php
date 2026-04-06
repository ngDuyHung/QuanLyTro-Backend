<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Bảng lease_members (Bảng trung gian)
     * * Làm nhiệm vụ nối Hợp đồng (leases) với Người ở ghép (tenants).
     * Mọi thông tin cá nhân đều lấy từ bảng tenants.
     */
    public function up(): void
    {
        Schema::create('lease_members', function (Blueprint $table) {
            $table->id()->comment('ID bản ghi');

            // Khóa ngoại trỏ về Hợp đồng
            $table->unsignedBigInteger('lease_id')
                ->comment('FK leases - Thuộc hợp đồng nào');

            // Khóa ngoại trỏ về Kho dữ liệu con người
            $table->unsignedBigInteger('tenant_id')
                ->comment('FK tenants - Trỏ về thông tin cư dân');

            $table->enum('relationship', ['spouse', 'child', 'parent', 'sibling', 'friend', 'other'])
                ->default('other')
                ->comment('Quan hệ với người đứng tên hợp đồng');

            $table->string('note', 255)->nullable()
                ->comment('Ghi chú thêm');

            $table->date('move_in_date')->nullable()
                ->comment('Ngày thành viên chuyển vào');

            $table->date('move_out_date')->nullable()
                ->comment('Ngày rời phòng, NULL = đang ở');

            $table->timestamp('created_at')->useCurrent()
                ->comment('Thời điểm tạo bản ghi');
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate()
                ->comment('Thời điểm cập nhật cuối cùng');

            // Thiết lập ràng buộc (Foreign Keys)
            $table->foreign('lease_id')
                ->references('id')
                ->on('leases')
                ->onDelete('cascade'); // Xóa hợp đồng thì xóa luôn danh sách thành viên

            $table->foreign('tenant_id')
                ->references('id')
                ->on('tenants')
                ->onDelete('restrict'); // Không cho phép xóa khách thuê nếu họ đang nằm trong danh sách này

            // Đánh index để truy vấn nhanh
            $table->index(['lease_id', 'tenant_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lease_members');
    }
};
