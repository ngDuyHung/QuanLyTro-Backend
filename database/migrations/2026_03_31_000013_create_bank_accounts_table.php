<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Bảng bank_accounts (tài khoản ngân hàng)
     *
     * Lưu thông tin tài khoản ngân hàng của chủ trọ để nhận tiền từ khách thuê.
     */
    public function up(): void
    {
        Schema::create('bank_accounts', function (Blueprint $table) {
            $table->id()->comment('ID tài khoản');
            $table->unsignedBigInteger('user_id')
                ->comment('FK users - tài khoản của chủ trọ nào');
            $table->string('account_name', 100)
                ->comment('Tên chủ tài khoản');
            $table->string('account_number', 30)->unique()
                ->comment('Số tài khoản');
            $table->string('bank_name', 100)
                ->comment('Tên ngân hàng (VD: Vietcombank, MB Bank)');
            $table->string('bank_code', 20)->nullable()
                ->comment('BIN hoặc short_name (VCB, TCB, 970415)');
            $table->string('branch', 100)->nullable()
                ->comment('Tên chi nhánh');
            $table->boolean('is_default')
                ->default(true)
                ->comment('Tài khoản mặc định để nhận tiền');
            $table->timestamps();

            $table->foreign('user_id')
                ->references('id')
                ->on('users')
                ->onDelete('cascade');

            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bank_accounts');
    }
};
