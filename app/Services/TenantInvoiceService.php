<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\Domain\BusinessException;
use App\Models\FinancialTransaction;
use App\Models\FinancialTransactionAllocation;
use App\Models\Invoice;
use App\Models\Lease;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\UploadedFile;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class TenantInvoiceService
{
    /**
     * Tự động lấy Hợp đồng đang hoạt động (Đại diện hoặc Ở ghép)
     */
    private function getActiveLease(int $userId, ?int $leaseId = null): Lease
    {
        // TỐI ƯU: Truy xuất ID trực tiếp thay vì dùng whereHas lồng nhau
        $tenantIds = \App\Models\Tenant::where('user_id', $userId)->pluck('id')->toArray();
        $memberLeaseIds = \App\Models\LeaseMember::whereIn('tenant_id', $tenantIds)->pluck('lease_id')->toArray();

        $query = Lease::where('status', 'active')
            ->where(function ($q) use ($tenantIds, $memberLeaseIds) {
                $q->whereIn('tenant_id', $tenantIds)
                    ->orWhereIn('id', $memberLeaseIds);
            });

        if ($leaseId) {
            $query->where('id', $leaseId);
        }

        $lease = $query->first();

        if (!$lease) {
            throw new BusinessException('Bạn chưa có hợp đồng thuê phòng nào đang hoạt động để xem hóa đơn.');
        }

        return $lease;
    }

    /**
     * Lấy danh sách hóa đơn của người thuê (loại bỏ hóa đơn nháp)
     */
    public function getTenantInvoices(int $userId, array $filters = [], int $perPage = 15, ?int $leaseId = null): LengthAwarePaginator
    {
        $lease = $this->getActiveLease($userId, $leaseId);

        $query = Invoice::query()
            ->with([
                'lease:id,room_id,tenant_id,start_date,end_date',
                'lease.room:id,property_id,name',
                'lease.room.property:id,name,address',
                'items',
                'allocations.financialTransaction',
            ])
            ->where('lease_id', $lease->id) // Đã sửa: Lọc thẳng qua ID hợp đồng
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
        if (!empty($filters['filter_month'])) {
            $query->where('period_from', 'like', $filters['filter_month'] . '-%');
        }

        return $query->latest()->paginate($perPage);
    }

    /**
     * Lấy chi tiết một hóa đơn của người thuê
     */
    public function getTenantInvoice(int $invoiceId, int $userId, ?int $leaseId = null): Invoice
    {
        $lease = $this->getActiveLease($userId, $leaseId);

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
            ->where('lease_id', $lease->id) // Đã sửa: Lọc thẳng qua ID hợp đồng
            ->where('status', '!=', 'draft')
            ->findOrFail($invoiceId);
    }

    public function submitProof(int $invoiceId, int $userId, array $data, UploadedFile $file, ?int $leaseId = null): Invoice
    {
        $invoice = $this->getTenantInvoice($invoiceId, $userId, $leaseId);

        // ... Các phần dưới (từ dòng if status) giữ nguyên không đổi ...
        if (in_array($invoice->status, ['paid', 'cancelled'])) {
            throw new BusinessException('Hóa đơn đã thanh toán hoặc đã hủy, không thể gửi minh chứng.');
        }

        if ((int)$data['amount'] > $invoice->remaining_amount) {
            throw new BusinessException('Số tiền báo cáo không được vượt quá số nợ còn lại.');
        }

        return DB::transaction(function () use ($invoice, $data, $file, $userId) {
            $extension = $file->extension();
            $filename = 'proof_' . time() . '_' . uniqid() . '.' . $extension;
            $path = $file->storeAs("proofs/invoices/{$invoice->id}", $filename, 'public');

            $transactionCode = 'PT-' . now()->format('YmdHis') . '-' . strtoupper(Str::random(4));

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
                'status' => 'pending',
                'transaction_date' => $data['transaction_date'],
                'description' => 'Khách thuê báo cáo thanh toán hóa đơn ' . $invoice->invoice_code,
                'note' => $data['note'] ?? null,
                'proof_image' => $path,
                'created_by' => $userId,
            ]);

            FinancialTransactionAllocation::create([
                'financial_transaction_id' => $transaction->id,
                'invoice_id' => $invoice->id,
                'allocated_amount' => $data['amount'],
                'allocation_type' => 'payment',
            ]);

            return $invoice->fresh(['allocations.financialTransaction']);
        });
    }
}
