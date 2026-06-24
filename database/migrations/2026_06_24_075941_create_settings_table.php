<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            
            // user_id có thể NULL (NULL = Mặc định của hệ thống)
            $table->foreignId('user_id')->nullable()->constrained('users')->cascadeOnDelete();
            
            $table->string('key'); 
            $table->longText('value')->nullable(); 
            $table->timestamps();

            // Đảm bảo 1 user chỉ có 1 key duy nhất (VD: 1 user chỉ có 1 'contract_template')
            $table->unique(['user_id', 'key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('settings');
    }
};