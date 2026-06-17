<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\Domain\BusinessException;
use App\Models\LeaseMember;
use App\Models\Room;
use App\Models\RoomResident;
use App\Models\Tenant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Throwable;

class TenantService
{
    public function createTenant(array $data, int $ownerId): Tenant
    {
        $storedPaths = [];

        try {
            return DB::transaction(function () use ($data, $ownerId, &$storedPaths): Tenant {
                $room = Room::with(['property', 'activeLease'])
                    ->whereHas('property', fn ($query) => $query->where('user_id', $ownerId))
                    ->findOrFail((int) $data['room_id']);

                $role = $data['role'] ?? 'member';

                $tenantData = [
                    'full_name' => $data['full_name'],
                    'email' => $data['email'] ?? null,
                    'phone' => $data['phone'],
                    'id_card_number' => $data['id_card_number'],
                    'user_id' => null,
                ];

                $tenant = Tenant::create($tenantData);

                if (isset($data['id_card_front_image'])) {
                    $ext = $data['id_card_front_image']->extension();

                    $tenant->id_card_front_image = $data['id_card_front_image']
                        ->storeAs("tenants/{$tenant->id}", "id_card_front.{$ext}", 'public');

                    $storedPaths[] = $tenant->id_card_front_image;
                }

                if (isset($data['id_card_back_image'])) {
                    $ext = $data['id_card_back_image']->extension();

                    $tenant->id_card_back_image = $data['id_card_back_image']
                        ->storeAs("tenants/{$tenant->id}", "id_card_back.{$ext}", 'public');

                    $storedPaths[] = $tenant->id_card_back_image;
                }

                $tenant->save();

                $activeLease = $room->activeLease;

                RoomResident::create([
                    'room_id' => $room->id,
                    'tenant_id' => $tenant->id,
                    'lease_id' => $activeLease?->id,
                    'role' => $role,
                    'status' => $activeLease ? 'active' : 'pending',
                    'move_in_date' => $data['move_in_date'] ?? now()->toDateString(),
                    'move_out_date' => null,
                    'note' => $data['note'] ?? null,
                ]);

                /*
                |--------------------------------------------------------------------------
                | Nếu phòng đã có hợp đồng active thì đồng bộ vào lease_members
                |--------------------------------------------------------------------------
                | - Người thêm từ danh mục khách thuê mặc định là member.
                | - Không tạo tài khoản đăng nhập.
                | - Nếu sau này chuyển đại diện thì làm ở chức năng riêng.
                */
                if ($activeLease) {
                    LeaseMember::firstOrCreate(
                        [
                            'lease_id' => $activeLease->id,
                            'tenant_id' => $tenant->id,
                        ],
                        [
                            'relationship' => 'other',
                            'note' => $data['note'] ?? null,
                            'move_in_date' => $data['move_in_date'] ?? now()->toDateString(),
                            'move_out_date' => null,
                        ]
                    );
                }

                return $tenant->load([
                    'currentResidence.room.property',
                    'currentResidence.lease',
                ]);
            });
        } catch (Throwable $exception) {
            foreach ($storedPaths as $path) {
                Storage::disk('public')->delete($path);
            }

            throw $exception;
        }
    }

    public function markTenantLeft(Tenant $tenant, int $ownerId, ?string $moveOutDate = null): Tenant
    {
        $residence = $tenant->roomResidents()
            ->whereIn('status', ['pending', 'active'])
            ->whereHas('room.property', fn ($query) => $query->where('user_id', $ownerId))
            ->latest()
            ->first();

        if (!$residence) {
            throw new BusinessException('Không tìm thấy thông tin cư trú hiện tại của khách thuê.');
        }

        $date = $moveOutDate ?: now()->toDateString();

        DB::transaction(function () use ($residence, $date): void {
            $residence->update([
                'status' => 'left',
                'move_out_date' => $date,
            ]);

            if ($residence->lease_id) {
                LeaseMember::where('lease_id', $residence->lease_id)
                    ->where('tenant_id', $residence->tenant_id)
                    ->whereNull('move_out_date')
                    ->update([
                        'move_out_date' => $date,
                    ]);
            }
        });

        return $tenant->refresh()->load([
            'currentResidence.room.property',
            'roomResidents.room.property',
        ]);
    }
}