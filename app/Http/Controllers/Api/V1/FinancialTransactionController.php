<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Exceptions\Domain\BusinessException;
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
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'property_id' => ['required', 'integer', 'exists:properties,id'],
            'room_id' => ['nullable', 'integer', 'exists:rooms,id'],
            'lease_id' => ['nullable', 'integer', 'exists:leases,id'],
            'tenant_id' => ['nullable', 'integer', 'exists:tenants,id'],
            'bank_account_id' => ['nullable', 'integer', 'exists:bank_accounts,id'],

            'direction' => ['required', 'in:income,expense'],

            'category' => [
                'required',
                'in:holding_deposit,security_deposit,deposit_forfeit,refund_security_deposit,damage_fee,repair,operation,other_income,other_expense',
            ],

            'accounting_type' => [
                'required',
                'in:revenue,liability_in,liability_out,expense,receivable_adjustment',
            ],

            'amount' => ['required', 'integer', 'min:1'],

            'method' => ['required', 'in:cash,bank_transfer,other'],

            'transaction_date' => ['nullable', 'date'],

            'transfer_content' => ['nullable', 'string', 'max:255'],
            'bank_transaction_code' => ['nullable', 'string', 'max:100'],

            'description' => ['nullable', 'string', 'max:255'],
            'note' => ['nullable', 'string'],
        ], [
            'property_id.required' => 'Vui lòng chọn khu nhà.',
            'property_id.exists' => 'Khu nhà không tồn tại.',
            'direction.required' => 'Vui lòng chọn loại thu hoặc chi.',
            'direction.in' => 'Loại giao dịch không hợp lệ.',
            'category.required' => 'Vui lòng chọn loại nghiệp vụ thu chi.',
            'category.in' => 'Loại nghiệp vụ thu chi không hợp lệ.',
            'accounting_type.required' => 'Vui lòng chọn bản chất kế toán.',
            'accounting_type.in' => 'Bản chất kế toán không hợp lệ.',
            'amount.required' => 'Vui lòng nhập số tiền.',
            'amount.integer' => 'Số tiền phải là số nguyên.',
            'amount.min' => 'Số tiền phải lớn hơn 0.',
            'method.required' => 'Vui lòng chọn phương thức thu chi.',
            'method.in' => 'Phương thức thu chi không hợp lệ.',
        ]);

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
    public function receiveInvoicePayment(Request $request, int $invoice): JsonResponse
    {
        $data = $request->validate([
            'amount' => ['required', 'integer', 'min:1'],

            'method' => ['required', 'in:cash,bank_transfer'],

            'bank_account_id' => ['nullable', 'integer', 'exists:bank_accounts,id'],

            'transaction_date' => ['nullable', 'date'],

            'transfer_content' => ['nullable', 'string', 'max:255'],
            'bank_transaction_code' => ['nullable', 'string', 'max:100'],

            'description' => ['nullable', 'string', 'max:255'],
            'note' => ['nullable', 'string'],
        ], [
            'amount.required' => 'Vui lòng nhập số tiền thanh toán.',
            'amount.integer' => 'Số tiền thanh toán phải là số nguyên.',
            'amount.min' => 'Số tiền thanh toán phải lớn hơn 0.',
            'method.required' => 'Vui lòng chọn phương thức thanh toán.',
            'method.in' => 'Phương thức thanh toán không hợp lệ.',
            'bank_account_id.exists' => 'Tài khoản ngân hàng không tồn tại.',
            'transaction_date.date' => 'Ngày thanh toán không hợp lệ.',
        ]);

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
    public function cancel(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'cancel_reason' => ['required', 'string', 'max:255'],
        ], [
            'cancel_reason.required' => 'Vui lòng nhập lý do hủy giao dịch.',
            'cancel_reason.max' => 'Lý do hủy không được vượt quá 255 ký tự.',
        ]);

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