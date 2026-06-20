<?php

declare(strict_types=1);

namespace App\Http\Resources\Invoice;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InvoiceResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,

            /*
             |--------------------------------------------------------------------------
             | Thông tin định danh
             |--------------------------------------------------------------------------
             */
            'invoice_code' => $this->invoice_code,
            'invoice_type' => $this->invoice_type,
            'status' => $this->status,
            'status_label' => $this->statusLabel((string) $this->status),

            /*
             |--------------------------------------------------------------------------
             | Liên kết chính
             |--------------------------------------------------------------------------
             */
            'lease_id' => $this->lease_id,
            'property_id' => $this->property_id,
            'room_id' => $this->room_id,

            /*
             |--------------------------------------------------------------------------
             | Kỳ hóa đơn
             |--------------------------------------------------------------------------
             */
            'period_from' => $this->period_from?->toDateString(),
            'period_to' => $this->period_to?->toDateString(),
            'issue_date' => $this->issue_date?->toDateString(),
            'due_date' => $this->due_date?->toDateString(),

            /*
             |--------------------------------------------------------------------------
             | Số tiền
             |--------------------------------------------------------------------------
             */
            'subtotal_amount' => (int) $this->subtotal_amount,
            'previous_debt_amount' => (int) $this->previous_debt_amount,
            'discount_amount' => (int) $this->discount_amount,
            'surcharge_amount' => (int) $this->surcharge_amount,
            'total_amount' => (int) $this->total_amount,
            'paid_amount' => (int) $this->paid_amount,
            'remaining_amount' => (int) $this->remaining_amount,

            /*
             |--------------------------------------------------------------------------
             | Mốc nghiệp vụ
             |--------------------------------------------------------------------------
             */
            'issued_at' => $this->issued_at?->toDateTimeString(),
            'locked_at' => $this->locked_at?->toDateTimeString(),
            'cancelled_at' => $this->cancelled_at?->toDateTimeString(),
            'cancel_reason' => $this->cancel_reason,

            'note' => $this->note,

            /*
             |--------------------------------------------------------------------------
             | Quan hệ
             |--------------------------------------------------------------------------
             */
            'property' => $this->whenLoaded('property', function (): ?array {
                if (!$this->property) {
                    return null;
                }

                return [
                    'id' => $this->property->id,
                    'name' => $this->property->name,
                    'address' => $this->property->address ?? null,
                ];
            }),

            'room' => $this->whenLoaded('room', function (): ?array {
                if (!$this->room) {
                    return null;
                }

                return [
                    'id' => $this->room->id,
                    'name' => $this->room->name,
                    'property_id' => $this->room->property_id,
                ];
            }),

            'lease' => $this->whenLoaded('lease', function (): ?array {
                if (!$this->lease) {
                    return null;
                }

                return [
                    'id' => $this->lease->id,
                    'room_id' => $this->lease->room_id,
                    'tenant_id' => $this->lease->tenant_id,
                    'start_date' => $this->lease->start_date?->toDateString(),
                    'end_date' => $this->lease->end_date?->toDateString(),
                    'billing_day' => $this->lease->billing_day,
                    'status' => is_object($this->lease->status)
                        ? $this->lease->status->value
                        : $this->lease->status,

                    'tenant' => $this->when(
                        $this->lease->relationLoaded('tenant') && $this->lease->tenant,
                        fn () => [
                            'id' => $this->lease->tenant->id,
                            'full_name' => $this->lease->tenant->full_name,
                            'phone' => $this->lease->tenant->phone,
                        ]
                    ),

                    'room' => $this->when(
                        $this->lease->relationLoaded('room') && $this->lease->room,
                        fn () => [
                            'id' => $this->lease->room->id,
                            'name' => $this->lease->room->name,
                            'property_id' => $this->lease->room->property_id,
                        ]
                    ),
                ];
            }),

            'items' => $this->whenLoaded('items', function () {
                return $this->items->map(fn ($item): array => [
                    'id' => $item->id,
                    'invoice_id' => $item->invoice_id,
                    'service_price_id' => $item->service_price_id,
                    'charge_type' => $item->charge_type,
                    'description' => $item->description,
                    'unit' => $item->unit,
                    'quantity' => (float) $item->quantity,
                    'unit_price_snapshot' => (int) $item->unit_price_snapshot,
                    'free_quantity_snapshot' => (float) $item->free_quantity_snapshot,
                    'amount' => (int) $item->amount,
                    'sort_order' => (int) $item->sort_order,
                ])->values();
            }),

            'allocations' => $this->whenLoaded('allocations', function () {
                return $this->allocations->map(fn ($allocation): array => [
                    'id' => $allocation->id,
                    'financial_transaction_id' => $allocation->financial_transaction_id,
                    'invoice_id' => $allocation->invoice_id,
                    'allocated_amount' => (int) $allocation->allocated_amount,
                    'allocation_type' => $allocation->allocation_type,
                    'allocated_at' => $allocation->allocated_at?->toDateTimeString(),

                    'financial_transaction' => $allocation->relationLoaded('financialTransaction') && $allocation->financialTransaction
                        ? [
                            'id' => $allocation->financialTransaction->id,
                            'transaction_code' => $allocation->financialTransaction->transaction_code,
                            'direction' => $allocation->financialTransaction->direction,
                            'category' => $allocation->financialTransaction->category,
                            'amount' => (int) $allocation->financialTransaction->amount,
                            'method' => $allocation->financialTransaction->method,
                            'status' => $allocation->financialTransaction->status,
                            'transaction_date' => $allocation->financialTransaction->transaction_date?->toDateTimeString(),
                        ]
                        : null,
                ])->values();
            }),

            /*
             |--------------------------------------------------------------------------
             | Thời gian hệ thống
             |--------------------------------------------------------------------------
             */
            'created_at' => $this->created_at?->toDateTimeString(),
            'updated_at' => $this->updated_at?->toDateTimeString(),
        ];
    }

    private function statusLabel(string $status): string
    {
        return match ($status) {
            'draft' => 'Nháp',
            'issued' => 'Đã phát hành',
            'partially_paid' => 'Thanh toán một phần',
            'paid' => 'Đã thanh toán',
            'overdue' => 'Quá hạn',
            'cancelled' => 'Đã hủy',
            default => $status,
        };
    }
}