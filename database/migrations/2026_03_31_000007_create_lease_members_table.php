<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Bảng lease_members (thành viên thuê / co-tenants / roommates)
     *
     * Lưu thông tin những người khác cùng ở trong 1 phòng
     * (không phải người ký hợp đồng chính).
     */
    public function up(): void
    {
        Schema::create('lease_members', function (Blueprint $table) {
            $table->id()->comment('ID thành viên');
            $table->unsignedBigInteger('lease_id')
                ->comment('FK leases - thành viên của hợp đồng nào');
            $table->string('full_name', 100)->comment('Họ và tên thành viên');
            $table->year('birth_year')->nullable()
                ->comment('Năm sinh (để phân biệt trẻ em/người lớn cho tính phí)');
            $table->enum('relationship', ['spouse', 'child', 'parent', 'sibling', 'friend', 'other'])
                ->default('other')
                ->comment('Quan hệ với người ký hợp đồng');
            $table->string('id_card_number', 20)->nullable()
                ->comment('Số CCCD/CMND nếu có');
            $table->string('phone', 15)->nullable()
                ->comment('Số điện thoại liên lạc');
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

            $table->foreign('lease_id')
                ->references('id')
                ->on('leases')
                ->onDelete('cascade');

            $table->index('lease_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lease_members');
    }
};
