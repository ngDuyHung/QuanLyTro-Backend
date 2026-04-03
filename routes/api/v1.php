<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\PropertyController;
use App\Http\Controllers\Api\V1\RoomController;
use App\Http\Controllers\Api\V1\TenantController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API V1 Routes
|--------------------------------------------------------------------------
|
| Tất cả route được đăng ký với prefix "v1" và middleware "force.json".
| Route yêu cầu xác thực sử dụng middleware "auth:sanctum".
|
*/

// ── Public routes ──────────────────────────────────────────────────────────
Route::middleware('throttle:auth')->group(function (): void {
    Route::post('auth/login', [AuthController::class, 'login'])->name('auth.login');
    Route::post('auth/register', [AuthController::class, 'register'])->name('auth.register');
});

// ── Authenticated routes ───────────────────────────────────────────────────
Route::middleware('auth:sanctum')->group(function (): void {
    Route::post('auth/logout', [AuthController::class, 'logout'])->name('auth.logout');
    Route::get('auth/me', [AuthController::class, 'me'])->name('auth.me');

    Route::apiResource('properties', PropertyController::class);

    // ── Rooms ──────────────────────────────────────────────────────────────
    // Danh sách & tạo phòng (nested dưới property)
    Route::get('properties/{propertyId}/rooms', [RoomController::class, 'index'])->name('properties.rooms.index');
    Route::post('properties/{propertyId}/rooms', [RoomController::class, 'store'])->name('properties.rooms.store');

    // Chi tiết, cập nhật, xóa, đổi trạng thái phòng
    Route::get('rooms/{room}', [RoomController::class, 'show'])->name('rooms.show');
    Route::put('rooms/{room}', [RoomController::class, 'update'])->name('rooms.update');
    Route::delete('rooms/{room}', [RoomController::class, 'destroy'])->name('rooms.destroy');
    Route::patch('rooms/{room}/status', [RoomController::class, 'updateStatus'])->name('rooms.status');

    // ── Tenants ──────────────────────────────────────────────────────────────
    Route::apiResource('tenants', TenantController::class);

    });
