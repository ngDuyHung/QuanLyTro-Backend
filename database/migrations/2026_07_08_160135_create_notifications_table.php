<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $table) {
            $table->id();
            // Chủ trọ (người tạo thông báo)
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();

            $table->string('title')->comment('Tiêu đề thông báo');
            $table->longText('content')->comment('Nội dung thông báo'); // Dùng longText vì nội dung Jodit HTML có thể dài

            $table->string('type', 50)->default('info')->comment('Loại thông báo'); // info, warning, billing
            $table->string('target_type', 50)->default('all')->comment('Loại đối tượng mục tiêu (all, property, room)'); // all, property, room
            $table->unsignedBigInteger('target_id')->nullable()->comment('ID của property hoặc room (nếu có)');
            $table->string('action_url', 255)->nullable()->comment('Đường dẫn mở khi click vào Web Push');

            $table->boolean('is_pinned')->default(false);
            $table->string('status', 50)->default('draft')->comment('Trạng thái thông báo'); // draft, published

            $table->timestamps();

            // Index để tăng tốc độ truy vấn
            $table->index(['user_id', 'status']);
            $table->index(['target_type', 'target_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};
