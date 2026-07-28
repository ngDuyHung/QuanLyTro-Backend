<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\AccountingLedgerController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\BankAccountController;
use App\Http\Controllers\Api\V1\DashboardController;
use App\Http\Controllers\Api\V1\LeaseController;
use App\Http\Controllers\Api\V1\LeaseMemberController;
use App\Http\Controllers\Api\V1\MeterReadingController;
use App\Http\Controllers\Api\V1\PropertyController;
use App\Http\Controllers\Api\V1\RoomController;
use App\Http\Controllers\Api\V1\SePayTransactionController;
use App\Http\Controllers\Api\V1\ServicePriceController;
use App\Http\Controllers\Api\V1\TenantController;
use App\Http\Controllers\Api\V1\FinancialTransactionController;
use App\Http\Controllers\Api\V1\ImportController;
use App\Http\Controllers\Api\V1\IncidentController;
use App\Http\Controllers\Api\V1\InvoiceController;
use App\Http\Controllers\Api\V1\NotificationController;
use App\Http\Controllers\Api\V1\OcrController;
use App\Http\Controllers\Api\V1\ReportController;
use App\Http\Controllers\Api\V1\RoomReservationController;
use App\Http\Controllers\Api\V1\SepayConfigController;
use App\Http\Controllers\Api\V1\SettingController;
use App\Http\Controllers\Api\V1\TenantIncidentController;
use App\Http\Controllers\Api\V1\TenantInvoiceController;
use App\Http\Controllers\Api\V1\TenantLeaseController;
use App\Http\Controllers\Api\V1\TenantMemberController;
use App\Http\Controllers\Api\V1\TenantNotificationController;
use App\Http\Controllers\Api\V1\TenantProfileController;
use App\Http\Controllers\Api\V1\TenantUtilityController;
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

    // ── Room Reservations (Cọc giữ chỗ) ────────────────────────────────────
    Route::post('room-reservations', [RoomReservationController::class, 'store'])->name('room-reservations.store');
    Route::patch('room-reservations/{id}/cancel', [RoomReservationController::class, 'cancel'])->name('room-reservations.cancel');
    Route::patch('room-reservations/{id}/extend', [RoomReservationController::class, 'extend'])->name('room-reservations.extend');

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
    Route::get('/leases/{id}/preview', [LeaseController::class, 'previewHtml']);

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

    // 1. Đặt các route không có tham số động (hoặc tiền tố đặc biệt) lên đầu tiên
    Route::get('service-prices/global', [ServicePriceController::class, 'indexGlobal'])->name('service-prices.index-global');
    Route::get('properties/{propertyId}/service-prices', [ServicePriceController::class, 'index'])->name('service-prices.index');

    // 2. Định nghĩa các thao tác CRUD chuẩn có chứa tham số {id} ở phía sau
    Route::post('service-prices', [ServicePriceController::class, 'store'])->name('service-prices.store');

    // 3. Nới lỏng kiểu dữ liệu nhận vào (chuyển int thành string) trong Controller (như hướng dẫn ở bước trước) 
    // để Laravel truyền mượt mà chuỗi số vào rồi ép kiểu, tránh lỗi declare(strict_types=1);
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
    Route::get('invoices/count-active', [InvoiceController::class, 'countActive']);

    Route::get('invoices/{id}', [InvoiceController::class, 'show'])->name('invoices.show');
    Route::put('invoices/{id}', [InvoiceController::class, 'update'])->name('invoices.update');
    Route::delete('invoices/{id}', [InvoiceController::class, 'destroy'])->name('invoices.destroy');

    Route::post('invoices/{id}/issue', [InvoiceController::class, 'issue'])->name('invoices.issue');
    Route::post('invoices/{id}/cancel', [InvoiceController::class, 'cancel'])->name('invoices.cancel');

    // Route xuất PDF hóa đơn (Có thể đặt tiền tố /invoices cho chuẩn RESTful)
    Route::get('/invoices/{id}/export-pdf', [SettingController::class, 'exportInvoicePdf']);
    Route::get('/invoices/{id}/preview', [InvoiceController::class, 'previewHtml']);

    // Route xuất hình ảnh hóa đơn (Có thể đặt tiền tố /invoices cho chuẩn RESTful)
    Route::get('/invoices/{id}/export-image', [InvoiceController::class, 'exportInvoiceImage']);
    Route::get('invoices/{id}/export-pdf-image', [InvoiceController::class, 'exportInvoicePdfImage']);

    // Group Route cho cấu hình mẫu hóa đơn
    Route::get('/settings/invoice-template', [SettingController::class, 'getInvoiceTemplate']);
    Route::post('/settings/invoice-template', [SettingController::class, 'saveInvoiceTemplate']);

    // ── Financial Transactions (Thu chi) ─────────────────────────────────────
    Route::get('financial-transactions', [FinancialTransactionController::class, 'index'])
        ->name('financial-transactions.index');

    Route::post('financial-transactions', [FinancialTransactionController::class, 'store'])
        ->name('financial-transactions.store');

    Route::get('financial-transactions/{id}', [FinancialTransactionController::class, 'show'])
        ->name('financial-transactions.show');

    Route::post('financial-transactions/{id}/cancel', [FinancialTransactionController::class, 'cancel'])
        ->name('financial-transactions.cancel');

    // Route cho phép chủ trọ duyệt giao dịch thu tiền từ khách thuê (chỉ áp dụng cho giao dịch PENDING)
    Route::post('financial-transactions/{id}/approve', [FinancialTransactionController::class, 'approve'])
        ->name('financial-transactions.approve');

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

    Route::get('invoices/{id}/payment-status', [SepayConfigController::class, 'checkPaymentStatus'])
        ->name('invoices.payment-status');

    // ── Route OCR (Quét CCCD) ────────────────────────────────
    Route::post('ocr/scan-id-card', [OcrController::class, 'scanIdCard'])->name('ocr.scan');
    // ── Route OCR (Quét hóa đơn điện/nước) ────────────────────────────────
    Route::post('ocr/scan-meter', [OcrController::class, 'scanMeter'])->name('ocr.scan-meter');

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

    // API Import dữ liệu tổng hợp (Khu nhà, Phòng, Hợp đồng)
    Route::post('imports/master-data', [ImportController::class, 'importMasterData']);
    // API Tải file Excel mẫu để điền dữ liệu import
    Route::get('imports/master-data/template', [ImportController::class, 'downloadTemplate']);

    // ── Route Notifications (Thông báo) ─────────────────────────────────────────────
    Route::apiResource('notifications', NotificationController::class);

    // ── Accounting Ledger (Chốt sổ kế toán) ────────────────────────────────────
    Route::apiResource('accounting-ledgers', AccountingLedgerController::class)->except(['update']);
    Route::post('accounting-ledgers/preview', [AccountingLedgerController::class, 'preview']);
    Route::get('/accounting-ledgers/{id}/preview-html', [AccountingLedgerController::class, 'previewHtml']);
    // Route cho Mẫu sổ kế toán S1a-HKD
    Route::get('/ledgers/{id}/export-pdf', [SettingController::class, 'exportLedgerPdf']);

    // ── Incidents (Sự cố) ─────────────────────────────────────────────────────
    // 1. CHUYỂN DÒNG NÀY LÊN ĐẦU TIÊN (Route tĩnh không có tham số)
    Route::get('incidents/count-active', [IncidentController::class, 'countActive']);

    Route::apiResource('incidents', IncidentController::class);
    Route::patch('incidents/{id}/process', [IncidentController::class, 'process']);
    Route::post('incidents/{id}/resolve', [IncidentController::class, 'resolve']);
    Route::patch('incidents/{id}/cancel', [IncidentController::class, 'cancel']);


    // ── Dashboard (Thống kê) ───────────────────────────────────────────────
    Route::get('dashboard', [DashboardController::class, 'index']);

    // ── Reports (Báo cáo Thống kê) ───────────────────────────────────────────────
    Route::prefix('reports')->name('reports.')->group(function () {
        Route::get('/financial', [App\Http\Controllers\Api\V1\ReportController::class, 'getFinancialReport'])->name('financial');
        Route::get('/ledger', [App\Http\Controllers\Api\V1\ReportController::class, 'getLedgerReport'])->name('ledger');
        Route::get('/debt', [App\Http\Controllers\Api\V1\ReportController::class, 'getDebtReport'])->name('debt');
        Route::get('/occupancy', [App\Http\Controllers\Api\V1\ReportController::class, 'getOccupancyReport'])->name('occupancy');

        // Route xuất Excel Báo cáo Sổ quỹ
        Route::get('/ledger/export/excel', [App\Http\Controllers\Api\V1\ReportController::class, 'exportLedgerExcel'])->name('ledger.export.excel');
    });

    // ── Tenant Dashboard ───────────────────────────────────────────────────
    Route::get('tenant/dashboard', [DashboardController::class, 'tenantIndex'])->name('tenant.dashboard');

    Route::prefix('tenant/invoices')->name('tenant.invoices.')->group(function () {
        Route::get('/', [TenantInvoiceController::class, 'index']);
        Route::get('/{id}', [TenantInvoiceController::class, 'show']);
        // API lấy thông tin ngân hàng của chủ trọ để hiển thị mã QR
        Route::get('/{id}/payment-config', [TenantInvoiceController::class, 'paymentConfig']);
        // API lấy bản in HTML hóa đơn
        Route::get('/{id}/preview-html', [TenantInvoiceController::class, 'previewHtml']);

        Route::get('/{id}/payment-status', [TenantInvoiceController::class, 'paymentStatus']);

        Route::post('/{id}/submit-proof', [TenantInvoiceController::class, 'submitProof']);
    });

    Route::prefix('tenant/utilities')->name('tenant.utilities.')->group(function () {
        Route::get('/', [TenantUtilityController::class, 'index']);
        Route::get('/current-readings', [TenantUtilityController::class, 'currentReadings']);
        Route::post('/submit-batch', [TenantUtilityController::class, 'submitBatch']);
        Route::get('/{id}', [TenantUtilityController::class, 'show']);
    });

    Route::prefix('tenant/leases')->name('tenant.leases.')->group(function () {
        Route::get('/', [TenantLeaseController::class, 'index']);
        Route::get('/{id}', [TenantLeaseController::class, 'show']);
        Route::get('/{id}/preview-html', [TenantLeaseController::class, 'previewHtml']);
    });

    Route::prefix('tenant/incidents')->name('tenant.incidents.')->group(function () {
        Route::get('/', [TenantIncidentController::class, 'index']);
        Route::post('/', [TenantIncidentController::class, 'store']);
        Route::get('/{id}', [TenantIncidentController::class, 'show']);
        Route::put('/{id}', [TenantIncidentController::class, 'update']);
        Route::patch('/{id}/cancel', [TenantIncidentController::class, 'cancel']);
    });

    Route::prefix('tenant/notifications')->name('tenant.notifications.')->group(function () {
        Route::get('/', [TenantNotificationController::class, 'index']);
        Route::get('/{id}', [TenantNotificationController::class, 'show']);
    });

    Route::get('tenant/profile', [TenantProfileController::class, 'show'])->name('tenant.profile');

    Route::prefix('tenant/members')->name('tenant.members.')->group(function () {
        Route::get('/', [TenantMemberController::class, 'index']);
        Route::post('/', [TenantMemberController::class, 'store']);
        // Sử dụng POST với _method=PUT hoặc PATCH từ phía Frontend để hỗ trợ upload File
        Route::post('/{member}', [TenantMemberController::class, 'update']);
        Route::delete('/{member}', [TenantMemberController::class, 'destroy']);
    });
});

// 1. THÊM ROUTE WEBHOOK VÀO PHẦN PUBLIC (Nằm ngoài auth:sanctum)
Route::post('sepay-webhook', [SePayTransactionController::class, 'webhook'])->name('sepay.webhook');
