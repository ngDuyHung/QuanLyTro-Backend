<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\BankAccountController;
use App\Http\Controllers\Api\V1\LeaseController;
use App\Http\Controllers\Api\V1\LeaseMemberController;
use App\Http\Controllers\Api\V1\MeterReadingController;
use App\Http\Controllers\Api\V1\PropertyController;
use App\Http\Controllers\Api\V1\RoomController;
use App\Http\Controllers\Api\V1\SePayTransactionController;
use App\Http\Controllers\Api\V1\ServicePriceController;
use App\Http\Controllers\Api\V1\TenantController;
use App\Http\Controllers\Api\V1\FinancialTransactionController;
use App\Http\Controllers\Api\V1\InvoiceController;
use App\Http\Controllers\Api\V1\OcrController;
use App\Http\Controllers\Api\V1\SepayConfigController;
use App\Http\Controllers\Api\V1\SettingController;
use App\Http\Controllers\Api\V1\UtilityController;
use App\Models\Room;
use Illuminate\Support\Facades\Route;
use Symfony\Component\Routing\Router;

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

    Route::post('auth/zalo/login', [AuthController::class, 'zaloLogin'])
        ->name('auth.zalo.login');
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
    Route::get('/rooms', [RoomController::class, 'all'])->name('rooms.all');
    Route::get('rooms/{room}', [RoomController::class, 'show'])->name('rooms.show');
    Route::put('rooms/{room}', [RoomController::class, 'update'])->name('rooms.update');
    Route::delete('rooms/{room}', [RoomController::class, 'destroy'])->name('rooms.destroy');
    Route::patch('rooms/{room}/status', [RoomController::class, 'updateStatus'])->name('rooms.status');

    // ── Tenants — chỉ xem/sửa/xóa (tạo mới qua POST /leases) ───────────────
    Route::post('tenants', [TenantController::class, 'store'])->name('tenants.store');
    Route::patch('tenants/{tenant}/leave', [TenantController::class, 'leave'])
        ->name('tenants.leave');
    Route::apiResource('tenants', TenantController::class)->except(['store']);

    // ── Leases (Hợp đồng thuê) ────────────────────────────────────────────
    Route::get('leases',                         [LeaseController::class, 'index'])->name('leases.index');
    Route::get('leases/{lease}',                 [LeaseController::class, 'show'])->name('leases.show');
    Route::post('leases',                        [LeaseController::class, 'store'])->name('leases.store');
    Route::put('leases/{lease}',                 [LeaseController::class, 'update'])->name('leases.update');
    Route::patch('leases/{lease}/end',           [LeaseController::class, 'end'])->name('leases.end');
    Route::patch('leases/{lease}/representative', [LeaseController::class, 'changeRepresentative'])->name('leases.representative');
    Route::delete('leases/{lease}',              [LeaseController::class, 'destroy'])->name('leases.destroy');

    // ── Lease Members (Thành viên hợp đồng) ──────────────────────────────
    Route::get('leases/{leaseId}/members',    [LeaseMemberController::class, 'index'])->name('leases.members.index');
    Route::post('leases/{leaseId}/members',   [LeaseMemberController::class, 'store'])->name('leases.members.store');
    Route::put('lease-members/{id}',          [LeaseMemberController::class, 'update'])->name('lease-members.update');
    Route::delete('lease-members/{id}',       [LeaseMemberController::class, 'destroy'])->name('lease-members.destroy');

    // ── Settings (Mẫu hợp đồng) ────────────────────────────────────────────
    Route::get('settings/contract-template', [SettingController::class, 'getContractTemplate']);
    Route::post('settings/contract-template', [SettingController::class, 'saveContractTemplate']); // Dùng POST hay PUT đều được
    Route::get('leases/{id}/export-pdf', [SettingController::class, 'exportLeasePdf']);

    // ── Service Prices (Giá dịch vụ) ───────────────────────────────────────
    Route::get('service-prices', [ServicePriceController::class, 'indexGlobal'])->name('service-prices.index-global'); // Danh sách giá mặc định (property_id = null)
    Route::get('properties/{propertyId}/service-prices', [ServicePriceController::class, 'index'])->name('service-prices.index'); // Giá áp dụng cho khu nhà (riêng + fallback mặc định)
    Route::post('service-prices', [ServicePriceController::class, 'store'])->name('service-prices.store');
    Route::get('service-prices/{id}', [ServicePriceController::class, 'show'])->name('service-prices.show');
    Route::put('service-prices/{id}', [ServicePriceController::class, 'update'])->name('service-prices.update');
    Route::delete('service-prices/{id}', [ServicePriceController::class, 'destroy'])->name('service-prices.destroy');

    // ── Meter Readings (Chỉ số tiêu thụ) ─────────────────────────────────
    Route::get('leases/{leaseId}/readings',  [MeterReadingController::class, 'index'])->name('leases.readings.index');
    Route::post('leases/{leaseId}/readings', [MeterReadingController::class, 'store'])->name('leases.readings.store');
    Route::get('readings/{id}',              [MeterReadingController::class, 'show'])->name('readings.show');
    Route::put('readings/{id}',              [MeterReadingController::class, 'update'])->name('readings.update');
    Route::delete('readings/{id}',           [MeterReadingController::class, 'destroy'])->name('readings.destroy');

    // ── Invoices (Hóa đơn) ───────────────────────────────────────────────

    // API tính toán trước số liệu điền vào Form
    Route::get('invoices/prepare', [InvoiceController::class, 'prepare'])->name('invoices.prepare');
    Route::get('invoices', [InvoiceController::class, 'index'])->name('invoices.index');
    Route::post('invoices', [InvoiceController::class, 'store'])->name('invoices.store');
    Route::post('invoices/bulk-create', [InvoiceController::class, 'bulkCreate'])->name('invoices.bulk-create');

    Route::get('invoices/{id}', [InvoiceController::class, 'show'])->name('invoices.show');
    Route::put('invoices/{id}', [InvoiceController::class, 'update'])->name('invoices.update');
    Route::delete('invoices/{id}', [InvoiceController::class, 'destroy'])->name('invoices.destroy');

    Route::post('invoices/{id}/issue', [InvoiceController::class, 'issue'])->name('invoices.issue');
    Route::post('invoices/{id}/cancel', [InvoiceController::class, 'cancel'])->name('invoices.cancel');

    // Group Route cho cấu hình mẫu hóa đơn
    Route::get('/settings/invoice-template', [SettingController::class, 'getInvoiceTemplate']);
    Route::post('/settings/invoice-template', [SettingController::class, 'saveInvoiceTemplate']);

    // Route xuất PDF hóa đơn (Có thể đặt tiền tố /invoices cho chuẩn RESTful)
    Route::get('/invoices/{id}/export-pdf', [SettingController::class, 'exportInvoicePdf']);

    // ── Financial Transactions (Thu chi) ─────────────────────────────────────
    Route::get('financial-transactions', [FinancialTransactionController::class, 'index'])
        ->name('financial-transactions.index');

    Route::post('financial-transactions', [FinancialTransactionController::class, 'store'])
        ->name('financial-transactions.store');

    Route::get('financial-transactions/{id}', [FinancialTransactionController::class, 'show'])
        ->name('financial-transactions.show');

    Route::post('financial-transactions/{id}/cancel', [FinancialTransactionController::class, 'cancel'])
        ->name('financial-transactions.cancel');

    // Ghi nhận thanh toán hóa đơn
    Route::post('invoices/{id}/receive-payment', [FinancialTransactionController::class, 'receiveInvoicePayment'])
        ->name('invoices.receive-payment');

    // ── SePay Transactions (Đối soát SePay) ─────────────────────────────────
    Route::get('sepay-transactions', [SePayTransactionController::class, 'index'])
        ->name('sepay-transactions.index');

    Route::get('sepay-transactions/{id}', [SePayTransactionController::class, 'show'])
        ->name('sepay-transactions.show');

    Route::post('sepay-transactions/{id}/retry', [SePayTransactionController::class, 'retry'])
        ->name('sepay-transactions.retry');

    Route::post('sepay-transactions/{id}/match', [SePayTransactionController::class, 'match'])
        ->name('sepay-transactions.match');

    Route::post('sepay-transactions/{id}/ignore', [SePayTransactionController::class, 'ignore'])
        ->name('sepay-transactions.ignore');


    // ── Route OCR (Quét CCCD) ────────────────────────────────
    Route::post('ocr/scan-id-card', [OcrController::class, 'scanIdCard'])->name('ocr.scan');

    // ── Utilities (Quản lý chỉ số Điện / Nước) ─────────────────────────────────
    Route::apiResource('utilities', UtilityController::class);

    // ── SePay Config (Cấu hình SePay) ─────────────────────────────────────────────
    Route::prefix('settings/sepay')->name('settings.sepay.')->group(function (): void {
        Route::get('/', [SepayConfigController::class, 'show'])->name('show');
        Route::post('/', [SepayConfigController::class, 'save'])->name('save');
        Route::delete('/', [SepayConfigController::class, 'destroy'])->name('destroy');
        Route::post('/test', [SepayConfigController::class, 'testConnection'])->name('test');
    });

    // ROUTE BANK ACCOUNTS VÀO PHẦN PRIVATE
    Route::apiResource('bank-accounts', BankAccountController::class);
});

// 1. THÊM ROUTE WEBHOOK VÀO PHẦN PUBLIC (Nằm ngoài auth:sanctum)
Route::post('sepay-webhook', [SePayTransactionController::class, 'webhook'])->name('sepay.webhook');
