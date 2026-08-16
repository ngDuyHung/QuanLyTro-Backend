<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\BankAccount\BankAccountResource;
use App\Http\Resources\Invoice\InvoiceResource;
use App\Services\SettingService;
use App\Services\TenantInvoiceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Models\BankAccount;
use App\Models\SepayConfig;

class TenantInvoiceController extends Controller
{
    public function __construct(
        private readonly TenantInvoiceService $tenantInvoiceService
    ) {}

    /**
     * Lấy danh sách hóa đơn cho màn hình của Khách thuê
     */
    public function index(Request $request): JsonResponse
    {
        $filters = $request->only(['status','filter_month', 'period_from', 'period_to', 'search']);

        // Đọc Lease ID từ Header
        $leaseIdHeader = $request->header('X-Lease-Id');
        $leaseId = $leaseIdHeader ? (int) $leaseIdHeader : null;

        $invoices = $this->tenantInvoiceService->getTenantInvoices(
            userId: $request->user()->id,
            filters: $filters,
            perPage: $request->integer('per_page', 15),
            leaseId: $leaseId // Truyền thêm leaseId
        );

        return InvoiceResource::collection($invoices)->response();
    }

    /**
     * Xem chi tiết hóa đơn
     */
    public function show(Request $request, int $id): JsonResponse
    {
        $leaseIdHeader = $request->header('X-Lease-Id');
        $leaseId = $leaseIdHeader ? (int) $leaseIdHeader : null;

        $invoice = $this->tenantInvoiceService->getTenantInvoice(
            invoiceId: $id,
            userId: $request->user()->id,
            leaseId: $leaseId // Truyền thêm leaseId
        );

        return (new InvoiceResource($invoice))->response();
    }

    /**
     * Lấy thông tin tài khoản ngân hàng & cấu hình QR của Chủ trọ 
     */
    public function paymentConfig(Request $request, int $id): JsonResponse
    {
        $leaseIdHeader = $request->header('X-Lease-Id');
        $leaseId = $leaseIdHeader ? (int) $leaseIdHeader : null;

        $invoice = $this->tenantInvoiceService->getTenantInvoice(
            invoiceId: $id,
            userId: $request->user()->id,
            leaseId: $leaseId // Truyền thêm leaseId
        );

        $landlordId = $invoice->property->user_id;

        $bankAccounts = BankAccount::query()
            ->where('user_id', $landlordId)
            ->where('is_default', 1)
            ->get();

        $sepayConfig = SepayConfig::forUser((int)$landlordId)->toArray();

        return response()->json([
            'success' => true,
            'data' => [
                'bank_accounts' => BankAccountResource::collection($bankAccounts),
                'sepay_config' => $sepayConfig
            ]
        ]);
    }

    /**
     * Xem bản in điện tử HTML 
     */
    public function previewHtml(Request $request, int $id, SettingService $settingService): JsonResponse
    {
        $leaseIdHeader = $request->header('X-Lease-Id');
        $leaseId = $leaseIdHeader ? (int) $leaseIdHeader : null;

        $invoice = $this->tenantInvoiceService->getTenantInvoice(
            invoiceId: $id,
            userId: $request->user()->id,
            leaseId: $leaseId // Truyền thêm leaseId
        );

        $landlordId = $invoice->property->user_id;
        $html = $settingService->compileInvoiceHtml($invoice->id, (int) $landlordId);

        return response()->json(['html' => $html]);
    }

    /**
     * Polling kiểm tra trạng thái thanh toán tự động
     */
    public function paymentStatus(Request $request, int $id): JsonResponse
    {
        $leaseIdHeader = $request->header('X-Lease-Id');
        $leaseId = $leaseIdHeader ? (int) $leaseIdHeader : null;

        $invoice = $this->tenantInvoiceService->getTenantInvoice(
            invoiceId: $id,
            userId: $request->user()->id,
            leaseId: $leaseId // Truyền thêm leaseId
        );

        return response()->json([
            'success' => true,
            'data' => [
                'paid_amount' => $invoice->paid_amount,
                'status' => $invoice->status,
            ]
        ]);
    }

    public function submitProof(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'amount' => ['required', 'numeric', 'min:1'],
            'transaction_date' => ['required', 'date'],
            'proof_image' => ['required', 'image', 'mimes:jpeg,png,jpg', 'max:5120'], 
            'note' => ['nullable', 'string', 'max:255'],
        ]);

        $leaseIdHeader = $request->header('X-Lease-Id');
        $leaseId = $leaseIdHeader ? (int) $leaseIdHeader : null;

        $invoice = $this->tenantInvoiceService->submitProof(
            invoiceId: $id,
            userId: $request->user()->id,
            data: $data,
            file: $request->file('proof_image'),
            leaseId: $leaseId // Truyền thêm leaseId
        );

        return response()->json([
            'message' => 'Đã gửi minh chứng thành công. Đang chờ duyệt.',
            'data' => new InvoiceResource($invoice)
        ]);
    }
}