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
        // Lọc các tham số từ query string chỉ lấy những tham số như status, period_from, period_to, search
        $filters = $request->only(['status', 'period_from', 'period_to', 'search']);

        $invoices = $this->tenantInvoiceService->getTenantInvoices(
            userId: $request->user()->id,
            filters: $filters,
            perPage: $request->integer('per_page', 15)
        );

        return InvoiceResource::collection($invoices)->response();
    }

    /**
     * Xem chi tiết hóa đơn (Dùng cho nửa bên trái của Modal thanh toán)
     */
    public function show(Request $request, int $id): JsonResponse
    {
        $invoice = $this->tenantInvoiceService->getTenantInvoice(
            invoiceId: $id,
            userId: $request->user()->id
        );

        return (new InvoiceResource($invoice))->response();
    }

    /**
     * Lấy thông tin tài khoản ngân hàng & cấu hình QR của Chủ trọ 
     * (Dùng cho nửa bên phải của Modal thanh toán)
     */
    public function paymentConfig(Request $request, int $id): JsonResponse
    {
        // 1. Lấy hóa đơn để xác định chủ trọ là ai
        $invoice = $this->tenantInvoiceService->getTenantInvoice(
            invoiceId: $id,
            userId: $request->user()->id
        );

        $landlordId = $invoice->property->user_id;

        // 2. Lấy danh sách ngân hàng đang kích hoạt của chủ trọ
        $bankAccounts = BankAccount::query()
            ->where('user_id', $landlordId)
            ->where('is_default', 1)
            ->get();

        // 3. Lấy cấu hình SePay của chủ trọ (Sửa lại cách gọi tại đây)
        $sepayConfig = SepayConfig::forUser((int)$landlordId)->toArray();

        return response()->json([
            'success' => true,
            'data' => [
                //Bọc $bankAccounts qua BankAccountResource để sinh sepay_qr_template
                'bank_accounts' => BankAccountResource::collection($bankAccounts),
                'sepay_config' => $sepayConfig
            ]
        ]);
    }

    /**
     * Xem bản in điện tử HTML (Chỉ dành cho hóa đơn đã thanh toán xong)
     */
    public function previewHtml(Request $request, int $id, SettingService $settingService): JsonResponse
    {
        // Kiểm tra quyền sở hữu hóa đơn của khách thuê trước
        $invoice = $this->tenantInvoiceService->getTenantInvoice(
            invoiceId: $id,
            userId: $request->user()->id
        );

        // Biên dịch HTML dựa trên setting của chủ trọ
        $landlordId = $invoice->property->user_id;
        $html = $settingService->compileInvoiceHtml($invoice->id, $landlordId);

        return response()->json(['html' => $html]);
    }

    /**
     * Polling kiểm tra trạng thái thanh toán tự động cho Khách thuê
     */
    public function paymentStatus(Request $request, int $id): JsonResponse
    {
        // 1. Kiểm tra hóa đơn này có thuộc về khách thuê đang đăng nhập hay không
        $invoice = $this->tenantInvoiceService->getTenantInvoice(
            invoiceId: $id,
            userId: $request->user()->id
        );

        // 2. Chỉ cần trả về thông tin paid_amount và status hiện tại của hóa đơn
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
            'proof_image' => ['required', 'image', 'mimes:jpeg,png,jpg', 'max:5120'], // Max 5MB
            'note' => ['nullable', 'string', 'max:255'],
        ]);

        $invoice = $this->tenantInvoiceService->submitProof(
            invoiceId: $id,
            userId: $request->user()->id,
            data: $data,
            file: $request->file('proof_image')
        );

        return response()->json([
            'message' => 'Đã gửi minh chứng thành công. Đang chờ duyệt.',
            'data' => new InvoiceResource($invoice)
        ]);
    }
}
