<?php

declare(strict_types=1);

namespace App\Http\Resources\Lease;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LeaseResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                   => $this->id,
            'room_id'              => $this->room_id,
            'tenant_id'            => $this->tenant_id,
            'start_date'           => $this->start_date?->toDateString(),
            'end_date'             => $this->end_date?->toDateString(),
            'billing_day'          => $this->billing_day,
            'deposit'              => $this->deposit,
            'room_price'           => $this->room_price,
            'move_out_notice_date' => $this->move_out_notice_date?->toDateString(),
            'status'               => $this->status?->value,
            'status_label'         => $this->status?->label(),
            'created_at'           => $this->created_at?->toISOString(),
            'updated_at'           => $this->updated_at?->toISOString(),

            // Relationship — chỉ hiện khi đã được load
            'room' => $this->whenLoaded('room', fn() => [
                'id'       => $this->room->id,
                'name'     => $this->room->name,
                'area'     => $this->room->area ? (float) $this->room->area : null,
                'status'   => $this->room->status?->value,
                'status_label' => $this->room->status?->label(),
                'current_price' => $this->room->current_price,
                'property' => $this->room->relationLoaded('property') ? [
                    'id'      => $this->room->property->id,
                    'name'    => $this->room->property->name,
                    'address' => $this->room->property->address ?? null,
                ] : null,
            ]),

            'tenant' => $this->whenLoaded('tenant', fn() => [
                'id'             => $this->tenant->id,
                'full_name'      => $this->tenant->full_name,
                'phone'          => $this->tenant->phone,
                'email'          => $this->tenant->email,
                'id_card_number' => $this->tenant->id_card_number,
            ]),

            'members' => $this->whenLoaded(
                'members',
                fn() =>
                $this->members->map(fn($member) => [
                    'id' => $member->id,
                    'tenant_id' => $member->tenant_id,
                    'full_name' => $member->tenant?->full_name,
                    'phone' => $member->tenant?->phone,
                    'relationship' => is_object($member->relationship)
                        ? $member->relationship->value
                        : $member->relationship,
                    'relationship_label' => is_object($member->relationship) && method_exists($member->relationship, 'label')
                        ? $member->relationship->label()
                        : null,
                    'move_in_date' => $member->move_in_date?->toDateString(),
                    'move_out_date' => $member->move_out_date?->toDateString(),
                    'note' => $member->note,
                ])
            ),

            'invoices' => $this->whenLoaded(
                'invoices',
                fn() =>
                $this->invoices->map(fn($invoice) => [
                    'id'            => $invoice->id,
                    'invoice_code'  => $invoice->invoice_code,
                    'status'        => $invoice->status?->value,
                    'status_label'  => $invoice->status?->label(),
                    'total_amount'  => $invoice->total_amount,
                    'billing_month' => $invoice->billing_month,
                ])
            ),

            'service_items' => $this->whenLoaded('serviceItems', function () {
                // Chỉ lấy các dịch vụ đang active (chưa hết hạn)
                return $this->serviceItems->whereNull('expiry_date')->map(fn($item) => [
                    'id'                 => $item->id,
                    'service_type'       => is_object($item->service_type) ? $item->service_type->value : $item->service_type,
                    'service_type_label' => is_object($item->service_type) && method_exists($item->service_type, 'label')
                        ? $item->service_type->label()
                        : (string) $item->service_type,
                    'quantity'           => $item->quantity,
                    'custom_price'       => $item->custom_price,
                    'effective_date'     => $item->effective_date, // Trả thêm xuống cho FE nếu cần
                ])->values(); // Reset lại key của array
            }),
        ];
    }
}
