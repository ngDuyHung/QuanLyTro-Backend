<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\Domain\BusinessException;
use App\Models\FinancialTransaction;
use App\Models\FinancialTransactionAllocation;
use App\Models\Invoice;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\UploadedFile;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class TenantInvoiceService
{
    /**
     * Lấy danh sách hóa đơn của người thuê (loại bỏ hóa đơn nháp)
     */
    public function getTenantInvoices(int $userId, array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = Invoice::query()
            ->with([
                'lease:id,room_id,tenant_id,start_date,end_date',
                'lease.room:id,property_id,name',
                'lease.room.property:id,name,address',
                'items',
                'allocations.financialTransaction',
            ])
            // Đã sửa: Truy vấn xuyên qua lease -> tenant -> user_id
            ->whereHas('lease.tenant', function (Builder $q) use ($userId): void {
                $q->where('user_id', $userId);
            })
            ->where('status', '!=', 'draft');

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        if (!empty($filters['period_from'])) {
            $query->whereDate('period_from', '>=', $filters['period_from']);
        }
        if (!empty($filters['period_to'])) {
            $query->whereDate('period_to', '<=', $filters['period_to']);
        }
        if (!empty($filters['search'])) {
            $query->where('invoice_code', 'like', '%' . $filters['search'] . '%');
        }

        return $query->latest()->paginate($perPage);
    }

    /**
     * Lấy chi tiết một hóa đơn của người thuê
     */
    public function getTenantInvoice(int $invoiceId, int $userId): Invoice
    {
        return Invoice::query()
            ->with([
                'lease.room.property',
                'lease.tenant',
                'property.user',
                'room',
                'items.servicePrice',
                'allocations.financialTransaction',
                'financialTransactions',
                'meterReadings',
            ])
            // Đã sửa: Truy vấn xuyên qua lease -> tenant -> user_id
            ->whereHas('lease.tenant', function (Builder $q) use ($userId): void {
                $q->where('user_id', $userId);
            })
            ->where('status', '!=', 'draft')
            ->findOrFail($invoiceId);
    }

    public function submitProof(int $invoiceId, int $userId, array $data, UploadedFile $file): Invoice
    {
        $invoice = $this->getTenantInvoice($invoiceId, $userId);

        if (in_array($invoice->status, ['paid', 'cancelled'])) {
            throw new BusinessException('Hóa đơn đã thanh toán hoặc đã hủy, không thể gửi minh chứng.');
        }

        if ((int)$data['amount'] > $invoice->remaining_amount) {
            throw new BusinessException('Số tiền báo cáo không được vượt quá số nợ còn lại.');
        }

        return DB::transaction(function () use ($invoice, $data, $file, $userId) {
            // 1. Lưu file ảnh minh chứng
            $extension = $file->extension();
            $filename = 'proof_' . time() . '_' . uniqid() . '.' . $extension;
            $path = $file->storeAs("proofs/invoices/{$invoice->id}", $filename, 'public');

            // 2. Tạo mã giao dịch
            $transactionCode = 'PT-' . now()->format('YmdHis') . '-' . strtoupper(Str::random(4));

            // 3. Tạo record thu tiền PENDING
            $transaction = FinancialTransaction::create([
                'property_id' => $invoice->property_id,
                'room_id' => $invoice->room_id,
                'lease_id' => $invoice->lease_id,
                'tenant_id' => $invoice->lease->tenant_id,
                'transaction_code' => $transactionCode,
                'direction' => 'income',
                'category' => 'invoice_payment',
                'accounting_type' => 'revenue',
                'amount' => $data['amount'],
                'method' => 'bank_transfer',
                'status' => 'pending', // QUAN TRỌNG: Ghi nhận trạng thái chờ duyệt
                'transaction_date' => $data['transaction_date'],
                'description' => 'Khách thuê báo cáo thanh toán hóa đơn ' . $invoice->invoice_code,
                'note' => $data['note'] ?? null,
                'proof_image' => $path, // Lưu đường dẫn ảnh
                'created_by' => $userId,
            ]);

            // 4. Móc nối giao dịch vào hóa đơn (Allocate)
            FinancialTransactionAllocation::create([
                'financial_transaction_id' => $transaction->id,
                'invoice_id' => $invoice->id,
                'allocated_amount' => $data['amount'],
                'allocation_type' => 'payment',
            ]);

            // KHÔNG GỌI refreshPaymentStatus() vì giao dịch chưa được chủ trọ 'confirmed'
            return $invoice->fresh(['allocations.financialTransaction']);
        });
    }
}
