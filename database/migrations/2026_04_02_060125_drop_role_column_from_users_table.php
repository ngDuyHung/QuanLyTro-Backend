<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Xóa cột role khỏi bảng users — phân quyền sẽ do Spatie Permission quản lý.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['role']);
            $table->dropColumn('role');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->enum('role', ['admin', 'landlord', 'tenant'])
                ->default('tenant')
                ->index()
                ->comment('Vai trò trong hệ thống (admin/landlord/tenant)')
                ->after('phone');
        });
    }
};
