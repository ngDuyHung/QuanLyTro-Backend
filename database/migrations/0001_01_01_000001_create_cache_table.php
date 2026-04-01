<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tạo bảng cache (lưu trữ cache session và query)
     * Tạo bảng cache_locks (lock cho cache operations)
     */
    public function up(): void
    {
        // ========== BẢNG CACHE ==========
        Schema::create('cache', function (Blueprint $table) {
            $table->string('key')->primary()->comment('Cache key');
            $table->mediumText('value')->comment('Giá trị cache (serialized)');
            $table->integer('expiration')->index()
                ->comment('Unix timestamp hết hạn');
        });

        // ========== BẢNG CACHE LOCKS ==========
        Schema::create('cache_locks', function (Blueprint $table) {
            $table->string('key')->primary()->comment('Lock key');
            $table->string('owner')->comment('Owner của lock');
            $table->integer('expiration')->index()
                ->comment('Unix timestamp hết hạn lock');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cache_locks');
        Schema::dropIfExists('cache');
    }
};
