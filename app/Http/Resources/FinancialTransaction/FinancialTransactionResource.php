<?php

declare(strict_types=1);

namespace App\Http\Resources\FinancialTransaction;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FinancialTransactionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,

            'transaction_code' => $this->transaction_code,

            'property_id' => $this->property_id,
            'room_id' => $this->room_id,
            'lease_id' => $this->lease_id,
            'tenant_id' => $this->tenant_id,
            'bank_account_id' => $this->bank_account_id,
            'sepay_transaction_id' => $this->sepay_transaction_id,

            'direction' => $this->direction,
            'direction_label' => $this->direction === 'income' ? 'Thu' : 'Chi',

            'category' => $this->category,
            'category_label' => $this->categoryLabel((string) $this->category),

            'accounting_type' => $this->accounting_type,

            'amount' => (int) $this->amount,

            'method' => $this->method,
            'method_label' => $this->methodLabel((string) $this->method),

            'status' => $this->status,
            'status_label' => $this->statusLabel((string) $this->status),

            'transaction_date' => $this->transaction_date?->toDateTimeString(),

            'transfer_content' => $this->transfer_content,
            'bank_transaction_code' => $this->bank_transaction_code,

            'confirmed_at' => $this->confirmed_at?->toDateTimeString(),
            'cancelled_at' => $this->cancelled_at?->toDateTimeString(),
            'cancel_reason' => $this->cancel_reason,

            'description' => $this->description,
            'note' => $this->note,

            'property' => $this->whenLoaded('property', fn () => $this->property ? [
                'id' => $this->property->id,
                'name' => $this->property->name,
            ] : null),

            'room' => $this->whenLoaded('room', fn () => $this->room ? [
                'id' => $this->room->id,
                'name' => $this->room->name,
            ] : null),

            'lease' => $this->whenLoaded('lease', fn () => $this->lease ? [
                'id' => $this->lease->id,
                'room_id' => $this->lease->room_id,
                'tenant_id' => $this->lease->tenant_id,
                'status' => is_object($this->lease->status)
                    ? $this->lease->status->value
                    : $this->lease->status,
            ] : null),

            'tenant' => $this->whenLoaded('tenant', fn () => $this->tenant ? [
                'id' => $this->tenant->id,
                'full_name' => $this->tenant->full_name,
                'phone' => $this->tenant->phone,
            ] : null),

            'bank_account' => $this->whenLoaded('bankAccount', fn () => $this->bankAccount ? [
                'id' => $this->bankAccount->id,
                'account_name' => $this->bankAccount->account_name,
                'account_number' => $this->bankAccount->account_number,
                'bank_name' => $this->bankAccount->bank_name,
                'bank_code' => $this->bankAccount->bank_code,
            ] : null),

            'sepay_transaction' => $this->whenLoaded('sepayTransaction', fn () => $this->sepayTransaction ? [
                'id' => $this->sepayTransaction->id,
                'provider_transaction_id' => $this->sepayTransaction->provider_transaction_id,
                'reference_code' => $this->sepayTransaction->reference_code,
                'content' => $this->sepayTransaction->content,
                'transfer_amount' => (int) $this->sepayTransaction->transfer_amount,
                'match_status' => $this->sepayTransaction->match_status,
            ] : null),

            'allocations' => $this->whenLoaded('allocations', function () {
                return $this->allocations->map(fn ($allocation): array => [
                    'id' => $allocation->id,
                    'invoice_id' => $allocation->invoice_id,
                    'allocated_amount' => (int) $allocation->allocated_amount,
                    'allocation_type' => $allocation->allocation_type,
                    'allocated_at' => $allocation->allocated_at?->toDateTimeString(),

                    'invoice' => $allocation->relationLoaded('invoice') && $allocation->invoice
                        ? [
                            'id' => $allocation->invoice->id,
                            'invoice_code' => $allocation->invoice->invoice_code,
                            'status' => $allocation->invoice->status,
                            'total_amount' => (int) $allocation->invoice->total_amount,
                            'paid_amount' => (int) $allocation->invoice->paid_amount,
                            'remaining_amount' => (int) $allocation->invoice->remaining_amount,
                        ]
                        : null,
                ])->values();
            }),

            'created_at' => $this->created_at?->toDateTimeString(),
            'updated_at' => $this->updated_at?->toDateTimeString(),
        ];
    }

    private function methodLabel(string $method): string
    {
        return match ($method) {
            'cash' => 'Tiền mặt',
            'bank_transfer' => 'Chuyển khoản',
            'sepay' => 'SePay',
            'other' => 'Khác',
            default => $method,
        };
    }

    private function statusLabel(string $status): string
    {
        return match ($status) {
            'pending' => 'Chờ xác nhận',
            'confirmed' => 'Đã xác nhận',
            'cancelled' => 'Đã hủy',
            'refunded' => 'Đã hoàn tiền',
            default => $status,
        };
    }

    private function categoryLabel(string $category): string
    {
        return match ($category) {
            'invoice_payment' => 'Thu tiền hóa đơn',
            'holding_deposit' => 'Thu cọc giữ chỗ',
            'security_deposit' => 'Thu tiền thế chân',
            'deposit_forfeit' => 'Tịch thu cọc',
            'refund_security_deposit' => 'Hoàn thế chân',
            'damage_fee' => 'Thu phí hư hỏng',
            'repair' => 'Chi sửa chữa',
            'operation' => 'Chi vận hành',
            'other_income' => 'Thu khác',
            'other_expense' => 'Chi khác',
            default => $category,
        };
    }
}