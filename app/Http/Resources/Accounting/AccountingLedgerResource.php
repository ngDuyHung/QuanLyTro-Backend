<?php

declare(strict_types=1);

namespace App\Http\Resources\Accounting;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AccountingLedgerResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'property_id' => $this->property_id,
            // Nếu có eager load property thì lấy tên, không thì báo "Toàn hệ thống"
            'property_name' => $this->whenLoaded('property', fn() => $this->property->name, 'Toàn hệ thống'),

            'period_type' => $this->period_type,
            'period_year' => $this->period_year,
            'period_month' => $this->period_month,

            'total_revenue' => $this->total_revenue,
            'note' => $this->note,

            'created_at' => $this->created_at?->toDateTimeString(),
            'updated_at' => $this->updated_at?->toDateTimeString(),

            // Danh sách chi tiết (chỉ map ra nếu được load từ controller)
            'details' => $this->whenLoaded('details', function () {
                return $this->details->map(fn($detail) => [
                    'id' => $detail->id,
                    'transaction_date' => $detail->transaction_date?->format('Y-m-d'),
                    'transaction_code' => $detail->transaction_code,
                    'description' => $detail->description,
                    'amount' => $detail->amount,
                ]);
            }),
        ];
    }
}
