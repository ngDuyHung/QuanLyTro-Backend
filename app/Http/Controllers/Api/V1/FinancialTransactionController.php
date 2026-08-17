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
use App\Models\FinancialTransactionAllocation;
use App\Models\Invoice;
use App\Models\Property;
use App\Services\FinancialTransactionService;
use App\Services\InvoiceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

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
        // TỐI ƯU 1: Lấy trước ID khu nhà
        $propertyIds = \App\Models\Property::where('user_id', $request->user()->id)->pluck('id');

        $transactions = FinancialTransaction::query()
            ->with([
                'property:id,name',
                'room:id,name,property_id',
                'tenant:id,full_name,phone',
                'allocations.invoice:id,invoice_code,status,total_amount,paid_amount,remaining_amount',
                // TỐI ƯU 2: Đã gỡ bỏ 'lease', 'bankAccount', 'sepayTransaction' khỏi danh sách load (vì Table không dùng)
            ])
            // TỐI ƯU 3: Thay whereHas bằng whereIn
            ->whereIn('property_id', $propertyIds)
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
            ->when($request->query('search'), function ($query, $search): void {
                $query->where(function ($q) use ($search) {
                    $q->where('transaction_code', 'like', '%' . $search . '%')
                        ->orWhereHas('allocations.invoice', function ($subQ) use ($search) {
                            $subQ->where('invoice_code', 'like', '%' . $search . '%');
                        });
                });
            })
            ->orderBy('transaction_date', 'desc')
            ->orderBy('id', 'desc')
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
        $propertyIds = \App\Models\Property::where('user_id', $request->user()->id)->pluck('id');

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
            ->whereIn('property_id', $propertyIds) // Tối ưu: Dùng whereIn
            ->findOrFail($id);

        return (new FinancialTransactionResource($transaction))->response();
    }

    /**
     * Hủy giao dịch thu chi.
     *
     * Chỉ áp dụng cho giao dịch PENDING hoặc CONFIRMED mà chưa cấn trừ vào hóa đơn.
     * Nếu CONFIRMED mà đã cấn trừ vào hóa đơn, phải tạo giao dịch điều chỉnh (adjustment) thay vì hủy trực tiếp.
     */
    public function cancel(CancelFinancialTransactionRequest $request, int $id): JsonResponse
    {
        $data = $request->validated();
        $propertyIds = \App\Models\Property::where('user_id', $request->user()->id)->pluck('id');

        $transaction = FinancialTransaction::query()
            ->with('allocations')
            ->whereIn('property_id', $propertyIds) // Tối ưu: Dùng whereIn
            ->findOrFail($id);

        if ($transaction->status === 'cancelled') {
            throw new BusinessException('Giao dịch này đã bị hủy trước đó.');
        }

        // CHỈ CHẶN NẾU GIAO DỊCH ĐÃ 'CONFIRMED' MÀ CÓ ALLOCATIONS
        if ($transaction->status === 'confirmed' && $transaction->allocations()->exists()) {
            throw new BusinessException('Không thể hủy trực tiếp giao dịch đã cấn vào hóa đơn. Vui lòng tạo giao dịch điều chỉnh.');
        }

        DB::transaction(function () use ($transaction, $data) {
            // NẾU LÀ PENDING: Xóa bỏ dòng cấn trừ (allocation) đi trước khi hủy
            if ($transaction->status === 'pending') {
                $transaction->allocations()->delete();
            }

            // Tiến hành hủy giao dịch
            $transaction->update([
                'status' => 'cancelled',
                'cancelled_at' => now(),
                'cancel_reason' => $data['cancel_reason'],
            ]);
        });

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
     * Duyệt giao dịch thu chi.
     *
     * Chỉ áp dụng cho giao dịch PENDING.
     * Nếu giao dịch đã CONFIRMED, không thể duyệt lại.
     */
    public function approve(Request $request, int $id, InvoiceService $invoiceService): JsonResponse
    {
        $propertyIds = Property::where('user_id', $request->user()->id)->pluck('id');

        // Tối ưu: Check quyền sở hữu qua property_id bằng whereIn
        $transaction = FinancialTransaction::whereIn('property_id', $propertyIds)->findOrFail($id);

        if ($transaction->status !== 'pending') {
            throw new BusinessException('Chỉ có thể duyệt giao dịch đang ở trạng thái chờ.');
        }

        DB::transaction(function () use ($transaction, $invoiceService) {
            // 2. Đổi trạng thái thành confirmed
            $transaction->update([
                'status' => 'confirmed',
                'confirmed_at' => now(),
            ]);

            // 3. Tìm hóa đơn đang được cấn trừ bởi giao dịch này
            $allocation = FinancialTransactionAllocation::where('financial_transaction_id', $transaction->id)->first();

            if ($allocation) {
                $invoice = Invoice::find($allocation->invoice_id);
                if ($invoice) {
                    // 4. GỌI LÕI GẠCH NỢ: Hàm này sẽ tính lại tổng tiền confirmed và cập nhật trạng thái hóa đơn
                    $invoiceService->refreshPaymentStatus($invoice);
                }
            }
        });

        return response()->json(['message' => 'Đã duyệt giao dịch thành công.']);
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
