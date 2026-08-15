<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\LeaseStatus;
use App\Exceptions\Domain\BusinessException;
use App\Models\Lease;
use App\Models\LeaseMember;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Throwable;

class TenantService
{
    // TIÊM AUTH SERVICE VÀO ĐÂY ĐỂ DÙNG TRONG HÀM CREATE PROFILE
    public function __construct(
        private readonly AuthService $authService
    ) {}

    /**
     * Tạo hồ sơ khách thuê, lưu ảnh CCCD và TỰ ĐỘNG cấp tài khoản User
     */
    public function createProfile(array $data, int $ownerId): Tenant
    {
        $storedPaths = [];

        try {
            return DB::transaction(function () use ($data, $ownerId, &$storedPaths): Tenant {

                // BƯỚC 1: XỬ LÝ TÀI KHOẢN APP (USER) TRƯỚC
                $userId = $data['user_id'] ?? null;

                if (empty($userId) && !empty($data['phone'])) {
                    // Nếu truyền lên SĐT mà chưa có ID, tự động gọi AuthService để tạo/lấy tài khoản
                    $accountUser = $this->authService->getOrCreateTenantUser([
                        'name'      => $data['full_name'],
                        'phone'     => $data['phone'],
                        'email'     => $data['email'] ?? null,
                        'password'  => $data['phone'], // Pass mặc định là SĐT nếu tạo mới
                    ]);
                    $userId = $accountUser->id;
                }

                // BƯỚC 2: TẠO HOẶC CẬP NHẬT HỒ SƠ CRM (TENANT)
                $attributes = [
                    'full_name' => $data['full_name'],
                    'user_id'   => $userId, // Gắn ID tài khoản vừa lấy được vào đây luôn
                ];
                if (array_key_exists('email', $data)) $attributes['email'] = $data['email'];
                if (array_key_exists('id_card_number', $data) && $data['id_card_number'] !== null) {
                    $attributes['id_card_number'] = $data['id_card_number'];
                }

                $tenant = Tenant::updateOrCreate(
                    [
                        'owner_id' => $ownerId,
                        'phone'    => $data['phone']
                    ],
                    $attributes
                );

                // BƯỚC 3: XỬ LÝ HÌNH ẢNH
                $imageUpdates = [];

                if (isset($data['id_card_front_image']) && $data['id_card_front_image'] instanceof UploadedFile) {
                    $extension = strtolower($data['id_card_front_image']->extension());
                    $frontPath = $data['id_card_front_image']->storeAs("tenants/{$tenant->id}", "id_card_front.{$extension}", 'public');
                    $storedPaths[] = $frontPath;
                    $imageUpdates['id_card_front_image'] = $frontPath;
                }

                if (isset($data['id_card_back_image']) && $data['id_card_back_image'] instanceof UploadedFile) {
                    $extension = strtolower($data['id_card_back_image']->extension());
                    $backPath = $data['id_card_back_image']->storeAs("tenants/{$tenant->id}", "id_card_back.{$extension}", 'public');
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
     * Tạo cư trú và lease_members cho người ở ghép.
     */
    public function createMemberResidence(Tenant $tenant, Lease $lease, array $data = []): LeaseMember
    {
        $leaseStatus = $lease->status?->value ?? $lease->status;

        if ($leaseStatus !== LeaseStatus::Active->value) {
            throw new BusinessException('Chỉ có thể thêm khách thuê vào phòng đang có hợp đồng hiệu lực.');
        }

        if ((int)$tenant->id === (int)$lease->tenant_id) {
            throw new BusinessException('Khách thuê này đang là người đứng tên đại diện của phòng này, không thể thêm làm người ở ghép.');
        }

        $activeRoommate = LeaseMember::where('tenant_id', $tenant->id)
            ->whereNull('move_out_date')
            ->whereHas('lease.room.property', fn($query) => $query->where('user_id', $tenant->owner_id))
            ->with('lease.room')
            ->first();

        if ($activeRoommate) {
            $roomName = $activeRoommate->lease?->room?->name ?? 'một phòng khác';
            throw new BusinessException("Khách thuê này hiện đang là người ở ghép tại {$roomName}. Một người chỉ được ở ghép 1 phòng tại 1 thời điểm. Vui lòng thao tác 'Rời phòng' cũ trước khi thêm vào phòng mới.");
        }

        $moveInDate = $data['move_in_date'] ?? now()->toDateString();

        return LeaseMember::create([
            'lease_id' => $lease->id,
            'tenant_id' => $tenant->id,
            'relationship' => $data['relationship'] ?? 'other',
            'note' => $data['note'] ?? null,
            'move_in_date' => $moveInDate,
            'move_out_date' => null,
        ]);
    }

    /**
     * Ghi nhận khách thuê rời phòng và đồng bộ lease_members.
     */
    public function markTenantLeft(Tenant $tenant, int $leaseId, ?string $moveOutDate = null): Tenant
    {
        $member = LeaseMember::where('tenant_id', $tenant->id)
            ->where('lease_id', $leaseId)
            ->whereNull('move_out_date')
            ->first();

        if (!$member) {
            throw new BusinessException('Không tìm thấy khách thuê này trong danh sách ở ghép của hợp đồng này.');
        }

        $date = $moveOutDate ?: now()->toDateString();
        $member->update(['move_out_date' => $date]);

        return $tenant->refresh()->load([
            'leases.room.property',
            'leaseMembers.lease.room.property',
        ]);
    }



    /**
     * Xóa toàn bộ ảnh CCCD của khách thuê.
     */
    public function deleteTenantFiles(Tenant|int $tenant): void
    {
        $tenantId = $tenant instanceof Tenant ? $tenant->id : $tenant;

        Storage::disk('public')->deleteDirectory("tenants/{$tenantId}");
    }

    /**
     * Khôi phục mật khẩu của khách thuê về mặc định (Số điện thoại)
     */
    public function resetTenantPassword(Tenant $tenant): void
    {
        if (!$tenant->user_id) {
            throw new BusinessException('Khách thuê này chưa được cấp tài khoản hệ thống.');
        }

        $user = User::find($tenant->user_id);

        if (!$user) {
            throw new BusinessException('Không tìm thấy dữ liệu tài khoản đăng nhập.');
        }

        // Đặt lại mật khẩu thành số điện thoại của Tenant
        $user->password = Hash::make($tenant->phone);
        $user->save();
    }
}
