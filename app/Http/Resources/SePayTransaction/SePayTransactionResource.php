<?php

declare(strict_types=1);

namespace App\Http\Resources\SePayTransaction;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SePayTransactionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,

            /*
             |--------------------------------------------------------------------------
             | Tài khoản ngân hàng
             |--------------------------------------------------------------------------
             */
            'bank_account_id' => $this->bank_account_id,

            'bank_account' => $this->whenLoaded('bankAccount', fn () => $this->bankAccount ? [
                'id' => $this->bankAccount->id,
                'account_name' => $this->bankAccount->account_name,
                'account_number' => $this->bankAccount->account_number,
                'bank_name' => $this->bankAccount->bank_name,
                'bank_code' => $this->bankAccount->bank_code,
            ] : null),

            /*
             |--------------------------------------------------------------------------
             | Định danh giao dịch SePay/ngân hàng
             |--------------------------------------------------------------------------
             */
            'provider_transaction_id' => $this->provider_transaction_id,
            'reference_code' => $this->reference_code,
            'gateway' => $this->gateway,

            /*
             |--------------------------------------------------------------------------
             | Thời gian
             |--------------------------------------------------------------------------
             */
            'transaction_time' => $this->transaction_time?->toDateTimeString(),
            'received_at' => $this->received_at?->toDateTimeString(),
            'processed_at' => $this->processed_at?->toDateTimeString(),

            /*
             |--------------------------------------------------------------------------
             | Thông tin tài khoản và nội dung giao dịch
             |--------------------------------------------------------------------------
             */
            'account_number' => $this->account_number,
            'sub_account' => $this->sub_account,

            'code' => $this->code,
            'content' => $this->content,
            'description' => $this->description,
            'matched_payment_code' => $this->matched_payment_code,

            /*
             |--------------------------------------------------------------------------
             | Số tiền
             |--------------------------------------------------------------------------
             */
            'transfer_type' => $this->transfer_type,
            'transfer_type_label' => $this->transfer_type === 'in' ? 'Tiền vào' : 'Tiền ra',

            'transfer_amount' => (int) $this->transfer_amount,
            'accumulated' => (int) $this->accumulated,

            /*
             |--------------------------------------------------------------------------
             | Đối soát
             |--------------------------------------------------------------------------
             */
            'match_status' => $this->match_status,
            'match_status_label' => $this->matchStatusLabel((string) $this->match_status),

            'matched_amount' => (int) $this->matched_amount,
            'unmatched_amount' => (int) $this->unmatched_amount,

            'error_message' => $this->error_message,

            /*
             |--------------------------------------------------------------------------
             | Giao dịch thu chi được tạo từ SePay
             |--------------------------------------------------------------------------
             */
            'financial_transactions' => $this->whenLoaded('financialTransactions', function () {
                return $this->financialTransactions->map(fn ($transaction): array => [
                    'id' => $transaction->id,
                    'transaction_code' => $transaction->transaction_code,
                    'property_id' => $transaction->property_id,
                    'room_id' => $transaction->room_id,
                    'lease_id' => $transaction->lease_id,
                    'tenant_id' => $transaction->tenant_id,

                    'direction' => $transaction->direction,
                    'category' => $transaction->category,
                    'accounting_type' => $transaction->accounting_type,

                    'amount' => (int) $transaction->amount,
                    'method' => $transaction->method,
                    'status' => $transaction->status,

                    'transaction_date' => $transaction->transaction_date?->toDateTimeString(),

                    'allocations' => $transaction->relationLoaded('allocations')
                        ? $transaction->allocations->map(fn ($allocation): array => [
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
                        ])->values()
                        : [],
                ])->values();
            }),

            /*
             |--------------------------------------------------------------------------
             | Raw payload
             |--------------------------------------------------------------------------
             |
             | Chỉ nên trả raw_payload khi show chi tiết/debug.
             | Nếu danh sách quá nặng thì controller có thể không load/không cần.
             */
            'raw_payload' => $this->when(
                $request->boolean('include_raw', false),
                $this->raw_payload
            ),

            'created_at' => $this->created_at?->toDateTimeString(),
            'updated_at' => $this->updated_at?->toDateTimeString(),
        ];
    }

    private function matchStatusLabel(string $status): string
    {
        return match ($status) {
            'unmatched' => 'Chưa đối soát',
            'matched' => 'Đã đối soát',
            'partially_matched' => 'Đối soát một phần',
            'duplicated' => 'Giao dịch trùng',
            'ignored' => 'Đã bỏ qua',
            'need_review' => 'Cần kiểm tra',
            default => $status,
        };
    }
}