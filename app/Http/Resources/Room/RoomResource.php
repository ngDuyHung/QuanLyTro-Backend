<?php

declare(strict_types=1);

namespace App\Http\Resources\Room;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;


class RoomResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,

            'property_id' => $this->property_id,

            'name' => $this->name,

            'floor_number' => $this->floor_number,

            'area' => $this->area ? (float) $this->area : null,

            'max_occupants' => $this->max_occupants,

            'current_price' => $this->current_price,
            
            'deposit_amount' => $this->deposit_amount,

            'billing_day' => $this->billing_day,

            'allow_shared' => (bool) $this->allow_shared,

            'is_public' => (bool) $this->is_public,

            'status' => $this->status?->value,

            'status_label' => $this->status?->label(),

            'description' => $this->description,

            'created_at' => $this->created_at?->toISOString(),

            'updated_at' => $this->updated_at?->toISOString(),

            'property' => $this->whenLoaded('property', fn() => [
                'id' => $this->property->id,
                'name' => $this->property->name,
                'address' => $this->property->address,
            ]),

            'images' => $this->whenLoaded(
                'images',
                fn() =>
                $this->images->map(fn($image) => [
                    'id' => $image->id,
                    'image_path' => $image->image_path,
                    'image_url' => $image->image_path
                        ? asset('storage/' . ltrim($image->image_path, '/'))
                        : null,
                    'is_cover' => (bool) $image->is_cover,
                    'sort_order' => $image->sort_order,
                ])->values()
            ),
            // Số lượng người đang ở hiện tại
            'current_occupants_count' => $this->whenLoaded(
                'currentResidents',
                fn() => $this->currentResidents->count()
            ),
            // Thông tin người đại diện (nếu có)
            'representative' => $this->whenLoaded('currentResidents', function () {
                $resident = $this->currentResidents
                    ->firstWhere('role', 'representative');

                if (!$resident || !$resident->tenant) {
                    return null;
                }

                return [
                    'resident_id' => $resident->id,
                    'role' => $resident->role,
                    'status' => $resident->status,
                    'move_in_date' => $resident->move_in_date?->toDateString(),

                    'tenant' => [
                        'id' => $resident->tenant->id,
                        'full_name' => $resident->tenant->full_name,
                        'name' => $resident->tenant->full_name,
                        'phone' => $resident->tenant->phone,
                        'email' => $resident->tenant->email,
                        'id_card_number' => $resident->tenant->id_card_number,
                    ],
                ];
            }),
            // Danh sách người đang ở hiện tại (nếu có)
            'current_residents' => $this->whenLoaded(
                'currentResidents',
                fn() => $this->currentResidents->map(fn($resident) => [
                    'resident_id' => $resident->id,
                    'role' => $resident->role,
                    'status' => $resident->status,
                    'move_in_date' => $resident->move_in_date?->toDateString(),
                    'move_out_date' => $resident->move_out_date?->toDateString(),
                    'note' => $resident->note,

                    'tenant' => $resident->tenant ? [
                        'id' => $resident->tenant->id,
                        'full_name' => $resident->tenant->full_name,
                        'name' => $resident->tenant->full_name,
                        'phone' => $resident->tenant->phone,
                        'email' => $resident->tenant->email,
                        'id_card_number' => $resident->tenant->id_card_number,
                    ] : null,
                ])->values()
            ),

            'tenant_name' => $this->whenLoaded('currentResidents', function () {
                $representative = $this->currentResidents
                    ->firstWhere('role', 'representative');

                return $representative?->tenant?->full_name;
            }),

            'tenant_phone' => $this->whenLoaded('currentResidents', function () {
                $representative = $this->currentResidents
                    ->firstWhere('role', 'representative');

                return $representative?->tenant?->phone;
            }),

            'pending_reservation' => $this->whenLoaded('reservations', function () {
                $reservationCollection = $this->reservations;

                // 2. Vì nó là Collection, nên dùng hàm isEmpty() để check
                if ($reservationCollection->isEmpty()) {
                    return null;
                }

                // 3. Rút lấy cái đầu tiên
                $reservation = $reservationCollection->first();

                return [
                    'id' => $reservation->id,
                    'tenant_name' => $reservation->tenant_name,
                    'tenant_phone' => $reservation->tenant_phone,
                    'deposit_amount' => $reservation->deposit_amount,
                    'expected_move_in_date' => $reservation->expected_move_in_date?->toDateString(),
                    'status' => $reservation->status,
                    'note' => $reservation->note,
                ];
            }),
            // có hóa đơn chưa thanh toán hay không
            'has_unpaid_invoice' => $this->whenLoaded('invoices', function () {
                return $this->invoices->isNotEmpty();
            }),

            // tổng số tiền còn nợ (chưa thanh toán) của các hóa đơn
            'unpaid_amount' => $this->whenLoaded('invoices', function () {
                return $this->invoices->sum('remaining_amount');
            }),
        ];
    }
}
