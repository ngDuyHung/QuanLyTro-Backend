<?php

declare(strict_types=1);

namespace App\Http\Resources\Tenant;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TenantResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        // 1. Lấy lịch sử làm ĐẠI DIỆN HỢP ĐỒNG (Dùng relationLoaded an toàn tuyệt đối)
        $repHistory = $this->relationLoaded('leases') ? $this->leases->map(function ($lease) {
            return [
                'id' => 'lease_' . $lease->id,
                'lease_id' => $lease->id,
                'property_name' => $lease->room->property->name ?? null,
                'room_name' => $lease->room->name ?? null,
                'role' => 'representative',
                'status' => ($lease->status->value ?? $lease->status) === 'active' ? 'active' : 'left',
                'move_in_date' => $lease->start_date ? $lease->start_date->format('Y-m-d') : null,
                'move_out_date' => $lease->end_date ? $lease->end_date->format('Y-m-d') : null,
                // TRẢ LẠI OBJECT ROOM CHO MODAL VIEW ĐỌC
                'room' => $lease->room ? [
                    'name' => $lease->room->name,
                    'property' => $lease->room->property ? ['name' => $lease->room->property->name] : null,
                ] : null,
            ];
        }) : collect([]);

        // 2. Lấy lịch sử làm NGƯỜI Ở GHÉP
        $memberHistory = $this->relationLoaded('leaseMembers') ? $this->leaseMembers->map(function ($member) {
            return [
                'id' => 'member_' . $member->id,
                'lease_id' => $member->lease_id,
                'property_name' => $member->lease->room->property->name ?? null,
                'room_name' => $member->lease->room->name ?? null,
                'role' => 'member',
                'status' => $member->move_out_date ? 'left' : 'active',
                'move_in_date' => $member->move_in_date ? $member->move_in_date->format('Y-m-d') : null,
                'move_out_date' => $member->move_out_date ? $member->move_out_date->format('Y-m-d') : null,
                // TRẢ LẠI OBJECT ROOM CHO MODAL VIEW ĐỌC
                'room' => $member->lease->room ? [
                    'name' => $member->lease->room->name,
                    'property' => $member->lease->room->property ? ['name' => $member->lease->room->property->name] : null,
                ] : null,
            ];
        }) : collect([]);

        // 3. Trộn lịch sử lại bằng concat (không dùng merge để tránh đè dữ liệu trùng index)
        $residenceHistory = $repHistory->concat($memberHistory)->sortByDesc('move_in_date')->values();

        // 4. Lọc lấy các phòng ĐANG Ở HIỆN TẠI
        $activeResidences = $residenceHistory->where('status', 'active');
        $currentResidence = $activeResidences->first();

        // 5. Trạng thái tổng quan
        $overallStatus = 'pending';
        if ($activeResidences->isNotEmpty()) {
            $overallStatus = 'active';
        } elseif ($residenceHistory->isNotEmpty()) {
            $overallStatus = 'left';
        }

        // 6. GỘP CHUNG TÊN PHÒNG VÀ MÃ HĐ (NẾU Ở NHIỀU PHÒNG) ĐỂ HIỂN THỊ TRÊN BẢNG
        $roomNames = $activeResidences->pluck('room_name')->filter()->unique()->implode(', ');
        $propertyNames = $activeResidences->pluck('property_name')->filter()->unique()->implode(', ');
        $leaseIds = $activeResidences->pluck('lease_id')->filter()->unique()->implode(', ');

        $primaryRole = $activeResidences->where('role', 'representative')->isNotEmpty() ? 'representative' : 'member';
        if ($activeResidences->isEmpty()) {
            $primaryRole = $residenceHistory->first()['role'] ?? 'member';
        }

        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'full_name' => $this->full_name,
            'name' => $this->full_name,
            'email' => $this->email,
            'phone' => $this->phone,
            'id_card_number' => $this->id_card_number,
            'cccd' => $this->id_card_number,
            'id_card_front_image' => $this->id_card_front_image ? asset('storage/' . $this->id_card_front_image) : null,
            'id_card_back_image' => $this->id_card_back_image ? asset('storage/' . $this->id_card_back_image) : null,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,

            // Modal View sẽ đọc dữ liệu từ đây
            'current_residence' => $currentResidence,
            'residence_history' => $residenceHistory,

            // Bảng danh sách sẽ đọc dữ liệu từ đây (Đã gộp bằng dấu phẩy)
            'room' => $roomNames ?: 'Chưa gắn phòng',
            'property' => $propertyNames ?: null,
            'lease_id' => $leaseIds ?: null,
            'role' => $primaryRole,
            'status' => $overallStatus,
            'move_in_date' => $currentResidence['move_in_date'] ?? null,
        ];
    }
}