<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\LeaseStatus;
use App\Exceptions\Domain\BusinessException;
use App\Models\Lease;
use App\Models\LeaseMember;
use App\Models\RoomResident;
use App\Models\Tenant;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Throwable;

class TenantService
{
    /**
     * Tạo hồ sơ khách thuê và lưu ảnh CCCD.
     */
    public function createProfile(array $data): Tenant
    {
        $storedPaths = [];

        try {
            return DB::transaction(function () use ($data, &$storedPaths): Tenant {
                $tenant = Tenant::create([
                    'full_name' => $data['full_name'],
                    'email' => $data['email'] ?? null,
                    'phone' => $data['phone'],
                    'id_card_number' => $data['id_card_number'],
                    'user_id' => null,
                ]);

                $imageUpdates = [];

                if (
                    isset($data['id_card_front_image']) &&
                    $data['id_card_front_image'] instanceof UploadedFile
                ) {
                    $extension = strtolower($data['id_card_front_image']->extension());

                    $frontPath = $data['id_card_front_image']->storeAs(
                        "tenants/{$tenant->id}",
                        "id_card_front.{$extension}",
                        'public'
                    );

                    $storedPaths[] = $frontPath;
                    $imageUpdates['id_card_front_image'] = $frontPath;
                }

                if (
                    isset($data['id_card_back_image']) &&
                    $data['id_card_back_image'] instanceof UploadedFile
                ) {
                    $extension = strtolower($data['id_card_back_image']->extension());

                    $backPath = $data['id_card_back_image']->storeAs(
                        "tenants/{$tenant->id}",
                        "id_card_back.{$extension}",
                        'public'
                    );

                    $storedPaths[] = $backPath;
                    $imageUpdates['id_card_back_image'] = $backPath;
                }

                if (!empty($imageUpdates)) {
                    $tenant->forceFill($imageUpdates)->save();
                }

                return $tenant->refresh();
            });
        } catch (Throwable $exception) {
            foreach ($storedPaths as $path) {
                Storage::disk('public')->delete($path);
            }

            throw $exception;
        }
    }

    /**
     * Tạo cư trú cho khách đại diện sau khi hợp đồng đã được tạo.
     */
    public function createRepresentativeResidence(
        Tenant $tenant,
        Lease $lease,
        ?string $moveInDate = null,
        ?string $note = null
    ): RoomResident {
        $this->ensureTenantHasNoActiveResidence($tenant);

        return RoomResident::create([
            'room_id' => $lease->room_id,
            'tenant_id' => $tenant->id,
            'lease_id' => $lease->id,
            'role' => 'representative',
            'status' => 'active',
            'move_in_date' => $moveInDate ?? $lease->start_date ?? now()->toDateString(),
            'move_out_date' => null,
            'note' => $note,
        ]);
    }

    /**
     * Tạo cư trú và lease_members cho người ở ghép.
     */
    public function createMemberResidence(
        Tenant $tenant,
        Lease $lease,
        array $data = []
    ): RoomResident {
        $this->ensureTenantHasNoActiveResidence($tenant);

        $leaseStatus = $lease->status?->value ?? $lease->status;

        if ($leaseStatus !== LeaseStatus::Active->value) {
            throw new BusinessException('Chỉ có thể thêm khách thuê vào phòng đang có hợp đồng hiệu lực.');
        }

        $moveInDate = $data['move_in_date'] ?? now()->toDateString();

        $residence = RoomResident::create([
            'room_id' => $lease->room_id,
            'tenant_id' => $tenant->id,
            'lease_id' => $lease->id,
            'role' => 'member',
            'status' => 'active',
            'move_in_date' => $moveInDate,
            'move_out_date' => null,
            'note' => $data['note'] ?? null,
        ]);

        LeaseMember::firstOrCreate(
            [
                'lease_id' => $lease->id,
                'tenant_id' => $tenant->id,
            ],
            [
                'relationship' => $data['relationship'] ?? 'other',
                'note' => $data['note'] ?? null,
                'move_in_date' => $moveInDate,
                'move_out_date' => null,
            ]
        );

        return $residence;
    }

    /**
     * Ghi nhận khách thuê rời phòng và đồng bộ lease_members.
     */
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
            'currentResidence.lease',
            'roomResidents.room.property',
        ]);
    }

    /**
     * Kiểm tra khách chưa có cư trú đang hoạt động.
     */
    private function ensureTenantHasNoActiveResidence(Tenant $tenant): void
    {
        $hasActiveResidence = $tenant->roomResidents()
            ->whereIn('status', ['pending', 'active'])
            ->exists();

        if ($hasActiveResidence) {
            throw new BusinessException('Khách thuê này đang có thông tin cư trú hiện tại.');
        }
    }

    /**
     * Xóa toàn bộ ảnh CCCD của khách thuê.
     */
    public function deleteTenantFiles(Tenant|int $tenant): void
    {
        $tenantId = $tenant instanceof Tenant ? $tenant->id : $tenant;

        Storage::disk('public')->deleteDirectory("tenants/{$tenantId}");
    }
}