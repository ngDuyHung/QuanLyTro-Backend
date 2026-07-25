<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;

use App\Exceptions\Domain\BusinessException;
use App\Http\Resources\SePayTransaction\SePayTransactionResource;
use App\Models\SePayTransaction;
use App\Services\SePayTransactionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SePayTransactionController extends Controller
{
    public function __construct(
        private readonly SePayTransactionService $sePayTransactionService
    ) {}

    /**
     * Danh sách giao dịch SePay của chủ trọ đang đăng nhập.
     *
     * Chỉ hiển thị giao dịch đã map được bank_account_id
     * với tài khoản ngân hàng thuộc user hiện tại.
     *
     * Nếu webhook không map được accountNumber với bank_accounts,
     * giao dịch sẽ có bank_account_id = NULL và không hiện ở màn hình chủ trọ.
     * Trường hợp đó nên xử lý ở màn hình admin/debug riêng.
     */
    public function index(Request $request): JsonResponse
    {
        $transactions = SePayTransaction::query()
            ->with([
                'bankAccount:id,user_id,account_name,account_number,bank_name,bank_code',
                'financialTransactions.allocations.invoice',
            ])
            ->whereHas('bankAccount', function ($query) use ($request): void {
                $query->where('user_id', $request->user()->id);
            })
            ->when($request->query('match_status'), function ($query, $status): void {
                $query->where('match_status', $status);
            })
            ->when($request->query('transfer_type'), function ($query, $transferType): void {
                $query->where('transfer_type', $transferType);
            })
            ->when($request->query('account_number'), function ($query, $accountNumber): void {
                $query->where('account_number', $accountNumber);
            })
            ->when($request->query('matched_payment_code'), function ($query, $paymentCode): void {
                $query->where('matched_payment_code', $paymentCode);
            })
            ->when($request->query('reference_code'), function ($query, $referenceCode): void {
                $query->where('reference_code', $referenceCode);
            })
            ->when($request->query('date_from'), function ($query, $dateFrom): void {
                $query->whereDate('transaction_time', '>=', $dateFrom);
            })
            ->when($request->query('date_to'), function ($query, $dateTo): void {
                $query->whereDate('transaction_time', '<=', $dateTo);
            })
            ->latest('transaction_time')
            ->paginate($request->integer('per_page', 15));

        return SePayTransactionResource::collection($transactions)->response();
    }

    /**
     * Xem chi tiết một giao dịch SePay.
     */
    public function show(Request $request, int $id): JsonResponse
    {
        $transaction = $this->findOwnedSePayTransaction(
            id: $id,
            userId: $request->user()->id
        );

        $transaction->load([
            'bankAccount',
            'financialTransactions.property',
            'financialTransactions.room',
            'financialTransactions.lease',
            'financialTransactions.tenant',
            'financialTransactions.allocations.invoice',
        ]);

        return (new SePayTransactionResource($transaction))->response();
    }

    /**
     * Thử xử lý/match lại giao dịch SePay.
     *
     * Dùng khi giao dịch đang:
     * - unmatched
     * - need_review
     * - partially_matched
     */
    public function retry(Request $request, int $id): JsonResponse
    {
        $transaction = $this->findOwnedSePayTransaction(
            id: $id,
            userId: $request->user()->id
        );

        if (!in_array($transaction->match_status, ['unmatched', 'need_review', 'partially_matched'], true)) {
            throw new BusinessException('Giao dịch này không ở trạng thái có thể xử lý lại.');
        }

        $transaction = $this->sePayTransactionService->processMatching($transaction);

        return (new SePayTransactionResource($transaction->load([
            'bankAccount',
            'financialTransactions.allocations.invoice',
        ])))->response();
    }

    /**
     * Đối soát thủ công giao dịch SePay vào một hóa đơn.
     *
     * Dùng khi hệ thống không tự parse được mã hóa đơn từ nội dung chuyển khoản,
     * hoặc chủ trọ muốn chọn hóa đơn thủ công.
     */
    public function match(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'invoice_id' => ['required', 'integer', 'exists:invoices,id'],
        ], [
            'invoice_id.required' => 'Vui lòng chọn hóa đơn cần đối soát.',
            'invoice_id.exists' => 'Hóa đơn không tồn tại.',
        ]);

        $this->findOwnedSePayTransaction(
            id: $id,
            userId: $request->user()->id
        );

        $transaction = $this->sePayTransactionService->manualMatch(
            sePayTransactionId: $id,
            invoiceId: (int) $data['invoice_id'],
            userId: $request->user()->id
        );

        return (new SePayTransactionResource($transaction->load([
            'bankAccount',
            'financialTransactions.allocations.invoice',
        ])))->response();
    }

    /**
     * Bỏ qua giao dịch SePay.
     *
     * Dùng cho trường hợp:
     * - giao dịch không liên quan hóa đơn,
     * - giao dịch test,
     * - giao dịch tiền ra,
     * - chủ trọ xác nhận không cần xử lý.
     */
    public function ignore(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'reason' => ['nullable', 'string', 'max:255'],
        ], [
            'reason.max' => 'Lý do bỏ qua không được vượt quá 255 ký tự.',
        ]);

        $transaction = $this->findOwnedSePayTransaction(
            id: $id,
            userId: $request->user()->id
        );

        if ($transaction->match_status === 'matched') {
            throw new BusinessException('Không thể bỏ qua giao dịch đã đối soát.');
        }

        $transaction->update([
            'match_status' => 'ignored',
            'processed_at' => now(),
            'error_message' => $data['reason'] ?? 'Chủ trọ bỏ qua giao dịch.',
        ]);

        return (new SePayTransactionResource($transaction->fresh([
            'bankAccount',
            'financialTransactions.allocations.invoice',
        ])))->response();
    }

    /**
     * Tìm giao dịch SePay thuộc user hiện tại.
     */
    private function findOwnedSePayTransaction(int $id, int $userId): SePayTransaction
    {
        return SePayTransaction::query()
            ->with('bankAccount')
            ->whereHas('bankAccount', function ($query) use ($userId): void {
                $query->where('user_id', $userId);
            })
            ->findOrFail($id);
    }

    /**
     * Endpoint Public để SePay gọi Webhook đến.
     */
    public function webhook(Request $request, \App\Services\SePayTransactionService $sePayTransactionService): JsonResponse
    {

        // 1. Nhận payload từ SePay
        $payload = $request->all();
        $accountNumber = $payload['accountNumber'] ?? null;
        $subAccount = $payload['subAccount'] ?? null; // Lấy thêm subAccount từ payload

        if (!$accountNumber && !$subAccount) {
            return response()->json([
                'success' => false,
                'message' => 'Thiếu thông tin số tài khoản từ SePay.'
            ], 400);
        }

        // 2. Tìm tài khoản ngân hàng để xác định user_id của chủ trọ
        // Ưu tiên khớp với subAccount (tài khoản ảo) trước, nếu không khớp thì tìm bằng accountNumber
        $bankAccount = \App\Models\BankAccount::query()
            ->when($subAccount, function ($query) use ($subAccount) {
                $query->where('account_number', $subAccount);
            })
            ->orWhere('account_number', $accountNumber)
            ->first();

        if (!$bankAccount) {
            return response()->json([
                'success' => false,
                'message' => 'Không tìm thấy tài khoản ngân hàng trên hệ thống.'
            ], 404);
        }

        $sepayConfig = \App\Models\SepayConfig::forUser($bankAccount->user_id);

        // Dùng trim() để loại bỏ khoảng trắng ẩn nếu vô tình nhập dư trong DB
        $savedKey = trim((string) $sepayConfig->apiToken());

        if (empty($savedKey)) {
            return response()->json([
                'success' => false,
                'message' => 'Chủ trọ chưa cấu hình SePay API Key.'
            ], 400);
        }

        $expectedToken = 'Apikey ' . $savedKey;
        $authHeader = $request->header('Authorization');

        // BẬT LOG ĐỂ KIỂM TRA
        // \Illuminate\Support\Facades\Log::info('--- BẮT ĐẦU TEST WEBHOOK ---');
        // \Illuminate\Support\Facades\Log::info('1. Header Postman gửi lên: [' . $authHeader . ']');
        // \Illuminate\Support\Facades\Log::info('2. Key lấy từ DB ghép lại  : [' . $expectedToken . ']');

        if (!$authHeader || $authHeader !== $expectedToken) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. Invalid API Key.'
            ], 401);
        }

        // Đẩy vào Service xử lý (Tự động tìm chủ trọ, đối soát...)
        $sePayTransactionService->handleWebhook($payload);

        // SePay yêu cầu trả về status 200 kèm json success
        return response()->json([
            'success' => true,
            'message' => 'Webhook received and processed successfully'
        ]);
    }
}
