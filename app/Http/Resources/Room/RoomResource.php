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

            'sort_order' => $this->sort_order,

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

            'amenities' => $this->amenities ?? [],

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
            'current_occupants_count' => $this->whenLoaded('activeLease', function () {
                if (!$this->activeLease) return 0;
                // 1 người đại diện + số người ở ghép
                $membersCount = $this->activeLease->relationLoaded('members') ? $this->activeLease->members->count() : 0;
                return 1 + $membersCount;
            }),

            // Thông tin người đại diện (nếu có)
            'representative' => $this->whenLoaded('activeLease', function () {
                if (!$this->activeLease || !$this->activeLease->relationLoaded('tenant') || !$this->activeLease->tenant) {
                    return null;
                }

                $tenant = $this->activeLease->tenant;

                return [
                    'resident_id' => 'rep_' . $tenant->id, // ID ảo để FE dùng làm key map
                    'role' => 'representative',
                    'status' => 'active',
                    'move_in_date' => $this->activeLease->start_date?->toDateString(),
                    'tenant' => [
                        'id' => $tenant->id,
                        'full_name' => $tenant->full_name,
                        'name' => $tenant->full_name,
                        'phone' => $tenant->phone,
                        'email' => $tenant->email,
                        'id_card_number' => $tenant->id_card_number,
                    ],
                ];
            }),

            // Danh sách toàn bộ người đang ở (Bao gồm Đại diện + Ở ghép)
            'current_residents' => $this->whenLoaded('activeLease', function () {
                if (!$this->activeLease) {
                    return [];
                }

                $residents = collect();

                // 1. Thêm người đại diện vào đầu danh sách
                if ($this->activeLease->relationLoaded('tenant') && $this->activeLease->tenant) {
                    $tenant = $this->activeLease->tenant;
                    $residents->push([
                        'resident_id' => 'rep_' . $tenant->id,
                        'role' => 'representative',
                        'status' => 'active',
                        'move_in_date' => $this->activeLease->start_date?->toDateString(),
                        'move_out_date' => null,
                        'note' => null,
                        'tenant' => [
                            'id' => $tenant->id,
                            'full_name' => $tenant->full_name,
                            'name' => $tenant->full_name,
                            'phone' => $tenant->phone,
                            'email' => $tenant->email,
                            'id_card_number' => $tenant->id_card_number,
                        ],
                    ]);
                }

                // 2. Thêm người ở ghép vào tiếp theo
                if ($this->activeLease->relationLoaded('members')) {
                    $members = $this->activeLease->members->map(fn($member) => [
                        'resident_id' => $member->id,
                        'role' => 'member',
                        'status' => 'active',
                        'move_in_date' => $member->move_in_date?->toDateString(),
                        'move_out_date' => $member->move_out_date?->toDateString(),
                        'note' => $member->note,
                        'tenant' => $member->tenant ? [
                            'id' => $member->tenant->id,
                            'full_name' => $member->tenant->full_name,
                            'name' => $member->tenant->full_name,
                            'phone' => $member->tenant->phone,
                            'email' => $member->tenant->email,
                            'id_card_number' => $member->tenant->id_card_number,
                        ] : null,
                    ]);

                    $residents = $residents->concat($members);
                }

                return $residents->values();
            }),

            'tenant_name' => $this->whenLoaded('activeLease', function () {
                return $this->activeLease?->tenant?->full_name;
            }),

            'tenant_phone' => $this->whenLoaded('activeLease', function () {
                return $this->activeLease?->tenant?->phone;
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
            'unpaid_amount' => $this->whenLoaded('invoices', function () {
                return $this->invoices->sum('remaining_amount'); //[cite: 1]
            }, 0),

            'payment_status' => $this->whenLoaded('invoices', function () {
                // Chỉ xét trạng thái thanh toán cho phòng đang có người thuê
                if ($this->status?->value !== 'occupied') {
                    return null;
                }

                // 1. Kiểm tra phòng có đang nợ tiền không
                $unpaidAmount = $this->invoices->sum('remaining_amount');
                if ($unpaidAmount > 0) {
                    return 'debt'; // Trạng thái: Đang nợ
                }

                // 2. Kiểm tra xem đã lập hóa đơn cho kỳ (tháng) hiện tại chưa
                if ($this->relationLoaded('latestInvoice')) {
                    $latest = $this->latestInvoice;

                    // Lấy định dạng Năm-Tháng hiện tại (VD: "2024-05")
                    $currentMonth = now()->format('Y-m');

                    // Kiểm tra hóa đơn mới nhất có kỳ bắt đầu (period_from) thuộc tháng này không
                    if (
                        !$latest ||
                        !$latest->period_from ||
                        $latest->period_from->format('Y-m') < $currentMonth
                    ) {
                        return 'unbilled'; // Trạng thái: Chưa lập hóa đơn tháng này
                    }
                }

                // 3. Nếu không nợ và kỳ này ĐÃ có hóa đơn
                return 'paid'; // Trạng thái: Đã thu đủ
            }),
        ];
    }
}
