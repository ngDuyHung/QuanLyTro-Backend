<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tạo bảng jobs (hàng đợi công việc async)
     * Tạo bảng job_batches (nhóm công việc)
     * Tạo bảng failed_jobs (công việc thất bại)
     *
     * Dùng cho: gửi email, xử lý ảnh, export file, v.v.
     */
    public function up(): void
    {
        // ========== BẢNG JOBS ==========
        Schema::create('jobs', function (Blueprint $table) {
            $table->id()->comment('ID công việc');
            $table->string('queue')->index()->comment('Tên queue (default, emails, exports)');
            $table->longText('payload')->comment('Dữ liệu công việc (serialized)');
            $table->unsignedTinyInteger('attempts')
                ->comment('Số lần đã thử execute');
            $table->unsignedInteger('reserved_at')->nullable()
                ->comment('Unix timestamp khi được worker lấy');
            $table->unsignedInteger('available_at')
                ->comment('Unix timestamp khi có thể execute');
            $table->unsignedInteger('created_at')
                ->comment('Unix timestamp tạo job');
        });

        // ========== BẢNG JOB BATCHES ==========
        Schema::create('job_batches', function (Blueprint $table) {
            $table->string('id')->primary()->comment('Batch ID');
            $table->string('name')->comment('Tên batch');
            $table->integer('total_jobs')->comment('Tổng số jobs');
            $table->integer('pending_jobs')->comment('Jobs chờ xử lý');
            $table->integer('failed_jobs')->comment('Jobs thất bại');
            $table->longText('failed_job_ids')->comment('IDs của jobs thất bại');
            $table->mediumText('options')->nullable()
                ->comment('Options (JSON)');
            $table->integer('cancelled_at')->nullable()
                ->comment('Unix timestamp cancel');
            $table->integer('created_at')->comment('Unix timestamp tạo');
            $table->integer('finished_at')->nullable()
                ->comment('Unix timestamp hoàn thành');
        });

        // ========== BẢNG FAILED JOBS ==========
        Schema::create('failed_jobs', function (Blueprint $table) {
            $table->id()->comment('ID job thất bại');
            $table->string('uuid')->unique()->comment('UUID unique của job');
            $table->text('connection')->comment('Tên connection (database, redis)');
            $table->text('queue')->comment('Tên queue');
            $table->longText('payload')->comment('Dữ liệu job');
            $table->longText('exception')->comment('Exception message + stack trace');
            $table->timestamp('failed_at')->useCurrent()
                ->comment('Thời điểm job thất bại');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('failed_jobs');
        Schema::dropIfExists('job_batches');
        Schema::dropIfExists('jobs');
    }
};
