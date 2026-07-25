<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Invoice;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;

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
}