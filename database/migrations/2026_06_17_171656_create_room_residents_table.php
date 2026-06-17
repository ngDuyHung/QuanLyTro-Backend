<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('room_residents', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('room_id')
                ->constrained('rooms')
                ->restrictOnDelete();

            $table->foreignId('tenant_id')
                ->constrained('tenants')
                ->restrictOnDelete();

            $table->foreignId('lease_id')
                ->nullable()
                ->constrained('leases')
                ->nullOnDelete();

            $table->enum('role', ['representative', 'member'])
                ->default('member')
                ->comment('Vai trò trong phòng: đại diện hoặc người ở ghép');

            $table->enum('status', ['pending', 'active', 'left'])
                ->default('active')
                ->comment('pending = chờ vào/chờ gắn hợp đồng, active = đang ở, left = đã rời');

            $table->date('move_in_date')->nullable();
            $table->date('move_out_date')->nullable();

            $table->string('note', 255)->nullable();

            $table->timestamps();

            $table->index(['room_id', 'status']);
            $table->index(['tenant_id', 'status']);
            $table->index(['lease_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('room_residents');
    }
};