<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tạo bảng personal_access_tokens (Laravel Sanctum)
     *
     * Dùng cho API token authentication. Mỗi người dùng có thể có nhiều token
     * với các quyền khác nhau (abilities) và thời gian hết hạn (expires_at).
     */
    public function up(): void
    {
        Schema::create('personal_access_tokens', function (Blueprint $table) {
            $table->id()->comment('ID token');
            $table->string('tokenable_type')->comment('Model type (App\\Models\\User)');
            $table->unsignedBigInteger('tokenable_id')
                ->comment('FK đến model (user ID)');
            $table->text('name')->comment('Tên token (api_token, web_token)');
            $table->string('token', 64)->unique()
                ->comment('Hash của token (256 ký tự hashed thành 64)');
            $table->text('abilities')->nullable()
                ->comment('JSON array quyền (["*"] = all permissions)');
            $table->timestamp('last_used_at')->nullable()
                ->comment('Lần sử dụng token cuối cùng');
            $table->timestamp('expires_at')->nullable()
                ->comment('NULL = không hết hạn');
            $table->timestamps();

            // Indexes
            $table->index(['tokenable_type', 'tokenable_id']);
            $table->index('expires_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('personal_access_tokens');
    }
};
