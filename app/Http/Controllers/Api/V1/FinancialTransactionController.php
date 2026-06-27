<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Exceptions\Domain\BusinessException;
use App\Http\Requests\FinancialTransaction\CancelFinancialTransactionRequest;
use App\Http\Requests\FinancialTransaction\ReceiveInvoicePaymentRequest;
use App\Http\Requests\FinancialTransaction\StoreFinancialTransactionRequest;
use App\Http\Resources\FinancialTransaction\FinancialTransactionResource;
use App\Models\FinancialTransaction;
use App\Models\Property;
use App\Services\FinancialTransactionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FinancialTransactionController extends Controller
{
    public function __construct(
        private readonly FinancialTransactionService $financialTransactionService
    ) {}

    /**
     * Danh sách thu chi của chủ trọ đang đăng nhập.
     *
     * Hỗ trợ filter:
     * - property_id
     * - room_id
     * - lease_id
     * - tenant_id
     * - direction
     * - category
     * - method
     * - status
     * - date_from/date_to
     */
    public function index(Request $request): JsonResponse
    {
        $transactions = FinancialTransaction::query()
            ->with([
                'property:id,name',
                'room:id,name,property_id',
                'lease:id,room_id,tenant_id,status',
                'tenant:id,full_name,phone',
                'bankAccount:id,account_name,account_number,bank_name,bank_code',
                'sepayTransaction:id,provider_transaction_id,reference_code,content,transfer_amount,match_status',
                'allocations.invoice:id,invoice_code,status,total_amount,paid_amount,remaining_amount',
            ])
            ->whereHas('property', function ($query) use ($request): void {
                $query->where('user_id', $request->user()->id);
            })
            ->when($request->query('property_id'), function ($query, $propertyId): void {
                $query->where('property_id', $propertyId);
            })
            ->when($request->query('room_id'), function ($query, $roomId): void {
                $query->where('room_id', $roomId);
            })
            ->when($request->query('lease_id'), function ($query, $leaseId): void {
                $query->where('lease_id', $leaseId);
            })
            ->when($request->query('tenant_id'), function ($query, $tenantId): void {
                $query->where('tenant_id', $tenantId);
            })
            ->when($request->query('direction'), function ($query, $direction): void {
                $query->where('direction', $direction);
            })
            ->when($request->query('category'), function ($query, $category): void {
                $query->where('category', $category);
            })
            ->when($request->query('method'), function ($query, $method): void {
                $query->where('method', $method);
            })
            ->when($request->query('status'), function ($query, $status): void {
                $query->where('status', $status);
            })
            ->when($request->query('date_from'), function ($query, $dateFrom): void {
                $query->whereDate('transaction_date', '>=', $dateFrom);
            })
            ->when($request->query('date_to'), function ($query, $dateTo): void {
                $query->whereDate('transaction_date', '<=', $dateTo);
            })
            ->latest('transaction_date')
            ->paginate($request->integer('per_page', 15));

        return FinancialTransactionResource::collection($transactions)->response();
    }

    /**
     * Tạo thu/chi thủ công.
     *
     * Dùng cho:
     * - thu cọc giữ chỗ,
     * - thu tiền thế chân,
     * - tịch thu cọc,
     * - hoàn thế chân,
     * - chi sửa chữa,
     * - chi vận hành,
     * - thu/chi khác.
     *
     * Không dùng method = sepay ở đây.
     * SePay sẽ đi qua webhook và SePayTransactionService.
     */
    public function store(StoreFinancialTransactionRequest $request): JsonResponse
    {
       // Lấy dữ liệu đã được validate an toàn từ Form Request
        $data = $request->validated();
        $this->assertPropertyOwned(
            propertyId: (int) $data['property_id'],
            userId: $request->user()->id
        );

        $transaction = $this->financialTransactionService->createGeneralTransaction(
            data: $data,
            userId: $request->user()->id
        );

        return (new FinancialTransactionResource($transaction->load([
            'property',
            'room',
            'lease',
            'tenant',
            'bankAccount',
            'sepayTransaction',
            'allocations.invoice',
        ])))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Ghi nhận thanh toán hóa đơn.
     *
     * Dùng cho:
     * POST /invoices/{id}/receive-payment
     */
    public function receiveInvoicePayment(ReceiveInvoicePaymentRequest $request, int $invoice): JsonResponse
    {
        $data = $request->validated();

        $transaction = $this->financialTransactionService->receiveInvoicePayment(
            invoiceId: $invoice,
            data: $data,
            userId: $request->user()->id
        );

        return (new FinancialTransactionResource($transaction))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Xem chi tiết một giao dịch thu chi.
     */
    public function show(Request $request, int $id): JsonResponse
    {
        $transaction = FinancialTransaction::query()
            ->with([
                'property:id,user_id,name',
                'room:id,name,property_id',
                'lease:id,room_id,tenant_id,status',
                'tenant:id,full_name,phone',
                'bankAccount:id,account_name,account_number,bank_name,bank_code',
                'sepayTransaction',
                'allocations.invoice',
            ])
            ->whereHas('property', function ($query) use ($request): void {
                $query->where('user_id', $request->user()->id);
            })
            ->findOrFail($id);

        return (new FinancialTransactionResource($transaction))->response();
    }

    /**
     * Hủy giao dịch thu chi.
     *
     * Tạm thời chỉ cho hủy giao dịch chưa cấn vào hóa đơn.
     * Nếu đã cấn tiền vào hóa đơn thì nên làm service điều chỉnh riêng,
     * tránh sai công nợ.
     */
    public function cancel(CancelFinancialTransactionRequest $request, int $id): JsonResponse
    {
        $data = $request->validated();

        $transaction = FinancialTransaction::query()
            ->with('allocations')
            ->whereHas('property', function ($query) use ($request): void {
                $query->where('user_id', $request->user()->id);
            })
            ->findOrFail($id);

        if ($transaction->status === 'cancelled') {
            throw new BusinessException('Giao dịch này đã bị hủy trước đó.');
        }

        if ($transaction->allocations()->exists()) {
            throw new BusinessException('Không thể hủy trực tiếp giao dịch đã cấn vào hóa đơn. Vui lòng tạo giao dịch điều chỉnh.');
        }

        $transaction->update([
            'status' => 'cancelled',
            'cancelled_at' => now(),
            'cancel_reason' => $data['cancel_reason'],
        ]);

        return (new FinancialTransactionResource($transaction->fresh([
            'property',
            'room',
            'lease',
            'tenant',
            'bankAccount',
            'sepayTransaction',
            'allocations.invoice',
        ])))->response();
    }

    /**
     * Đảm bảo khu nhà thuộc về user đang đăng nhập.
     */
    private function assertPropertyOwned(int $propertyId, int $userId): void
    {
        $exists = Property::query()
            ->where('id', $propertyId)
            ->where('user_id', $userId)
            ->exists();

        if (!$exists) {
            throw new BusinessException('Bạn không có quyền thao tác trên khu nhà này.');
        }
    }
}