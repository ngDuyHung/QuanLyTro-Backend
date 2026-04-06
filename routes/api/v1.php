<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\LeaseController;
use App\Http\Controllers\Api\V1\LeaseMemberController;
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

    // ── Tenants — chỉ xem/sửa/xóa (tạo mới qua POST /leases) ───────────────
    Route::apiResource('tenants', TenantController::class)->except(['store']);

    // ── Leases (Hợp đồng thuê) ────────────────────────────────────────────
    Route::get('leases',                         [LeaseController::class, 'index'])->name('leases.index');
    Route::get('leases/{lease}',                 [LeaseController::class, 'show'])->name('leases.show');
    Route::post('leases',                        [LeaseController::class, 'store'])->name('leases.store');
    Route::put('leases/{lease}',                 [LeaseController::class, 'update'])->name('leases.update');
    Route::patch('leases/{lease}/end',           [LeaseController::class, 'end'])->name('leases.end');
    Route::patch('leases/{lease}/representative',[LeaseController::class, 'changeRepresentative'])->name('leases.representative');
    Route::delete('leases/{lease}',              [LeaseController::class, 'destroy'])->name('leases.destroy');

    // ── Lease Members (Thành viên hợp đồng) ──────────────────────────────
    Route::get('leases/{leaseId}/members',    [LeaseMemberController::class, 'index'])->name('leases.members.index');
    Route::post('leases/{leaseId}/members',   [LeaseMemberController::class, 'store'])->name('leases.members.store');
    Route::put('lease-members/{id}',          [LeaseMemberController::class, 'update'])->name('lease-members.update');
    Route::delete('lease-members/{id}',       [LeaseMemberController::class, 'destroy'])->name('lease-members.destroy');
});
