<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\Domain\BusinessException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\StoreTenantMemberRequest;
use App\Http\Requests\Tenant\UpdateTenantMemberRequest;
use App\Http\Resources\LeaseMember\LeaseMemberResource;
use App\Models\Lease;
use App\Models\LeaseMember;
use App\Models\Tenant;
use App\Services\AuthService;
use App\Services\TenantService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class TenantMemberController extends Controller
{
    public function __construct(
        private readonly TenantService $tenantService,
        private readonly AuthService $authService
    ) {}

    /**
     * Tự động tìm Hợp đồng đang Active của khách thuê đăng nhập (hỗ trợ cả ở ghép)
     */
    private function getActiveLease(int $userId, ?int $leaseId = null): Lease
    {
        // TỐI ƯU: Truy xuất ID trực tiếp thay vì dùng whereHas lồng nhau
        $tenantIds = \App\Models\Tenant::where('user_id', $userId)->pluck('id')->toArray();
        $memberLeaseIds = \App\Models\LeaseMember::whereIn('tenant_id', $tenantIds)->pluck('lease_id')->toArray();

        $query = Lease::with(['room.property'])
            ->where('status', 'active')
            ->where(function ($q) use ($tenantIds, $memberLeaseIds) {
                $q->whereIn('tenant_id', $tenantIds)
                    ->orWhereIn('id', $memberLeaseIds);
            });

        if ($leaseId) {
            $query->where('id', $leaseId);
        }

        $lease = $query->first();

        if (!$lease) {
            throw new BusinessException('Bạn không có hợp đồng nào đang hiệu lực để quản lý thành viên.');
        }

        return $lease;
    }

    /**
     * Lấy danh sách thành viên đang ở ghép trong phòng
     */
    public function index(Request $request): JsonResponse
    {
        $leaseIdHeader = $request->header('X-Lease-Id');
        $lease = $this->getActiveLease($request->user()->id, $leaseIdHeader ? (int)$leaseIdHeader : null);

        $members = $lease->members()->with('tenant')->whereNull('move_out_date')->get();
        return LeaseMemberResource::collection($members)->response();
    }

    /**
     * Thêm người ở ghép mới
     */
    public function store(StoreTenantMemberRequest $request): JsonResponse
    {
        $leaseIdHeader = $request->header('X-Lease-Id');
        $lease = $this->getActiveLease($request->user()->id, $leaseIdHeader ? (int)$leaseIdHeader : null);

        return DB::transaction(function () use ($request, $lease): JsonResponse {
            // 1. Kiểm tra sức chứa (Max Occupants)
            if ((int) $lease->room->max_occupants > 0) {
                // currentCount = 1 (Người đại diện) + số người đang ở trong lease_members
                $currentCount = $lease->members()->whereNull('move_out_date')->count() + 1;

                if ($currentCount >= (int) $lease->room->max_occupants) {
                    throw new BusinessException(
                        "Phòng của bạn chỉ được phép ở tối đa {$lease->room->max_occupants} người. Hiện tại đã có {$currentCount} người."
                    );
                }
            }

            $data = $request->validated();

            // --- BỔ SUNG: CẤP TÀI KHOẢN TỰ ĐỘNG ---
            $accountTenant = $this->authService->getOrCreateTenantUser([
                'name'      => $data['full_name'],
                'phone'     => $data['phone'],
                'email'     => $data['email'] ?? null,
                'password'  => $data['phone'],
            ]);
            $data['user_id'] = $accountTenant->id;
            // ------------------------------------

            // 2. Tạo Profile Tenant (Đưa mảng $data đã có user_id vào)
            // lấy id chủ trọ để gắn vào tenant (để sau này chủ trọ có thể quản lý được)
            $landlordId = $lease->room->property->user_id;
            $tenant = $this->tenantService->createProfile($data, $landlordId);

            // 3. Gắn vào phòng và hợp đồng (tái sử dụng Core Service)
            $this->tenantService->createMemberResidence(
                tenant: $tenant,
                lease: $lease,
                data: [
                    'relationship' => $request->input('relationship'),
                    'note' => $request->input('note'),
                    'move_in_date' => $request->input('move_in_date', now()->toDateString()),
                ]
            );

            $member = LeaseMember::with('tenant')
                ->where('lease_id', $lease->id)
                ->where('tenant_id', $tenant->id)
                ->firstOrFail();

            return (new LeaseMemberResource($member))
                ->additional(['message' => 'Đã thêm thành viên mới thành công.'])
                ->response()
                ->setStatusCode(201);
        });
    }

    /**
     * Cập nhật thông tin thành viên (Cập nhật cả bảng Tenant và bảng Pivot LeaseMember)
     */
    public function update(UpdateTenantMemberRequest $request, int $tenantId): JsonResponse
    {
        $leaseIdHeader = $request->header('X-Lease-Id');
        $lease = $this->getActiveLease($request->user()->id, $leaseIdHeader ? (int)$leaseIdHeader : null);

        // Đảm bảo thành viên này thuộc hợp đồng của người đăng nhập
        $member = LeaseMember::with('tenant')
            ->where('lease_id', $lease->id)
            ->where('tenant_id', $tenantId)
            ->firstOrFail();

        $data = $request->validated();

        DB::transaction(function () use ($request, $data, $member, $tenantId) {
            // 1. Cập nhật thông tin bảng tenants (Tên, CCCD, Ảnh...)
            $tenant = $member->tenant;

            if ($request->hasFile('id_card_front_image')) {
                if ($tenant->id_card_front_image) Storage::disk('public')->delete($tenant->id_card_front_image);
                $ext = $request->file('id_card_front_image')->extension();
                $data['id_card_front_image'] = $request->file('id_card_front_image')->storeAs("tenants/{$tenantId}", "id_card_front.{$ext}", 'public');
            }

            if ($request->hasFile('id_card_back_image')) {
                if ($tenant->id_card_back_image) Storage::disk('public')->delete($tenant->id_card_back_image);
                $ext = $request->file('id_card_back_image')->extension();
                $data['id_card_back_image'] = $request->file('id_card_back_image')->storeAs("tenants/{$tenantId}", "id_card_back.{$ext}", 'public');
            }

            $tenant->update(\Illuminate\Support\Arr::only($data, ['full_name', 'phone', 'id_card_number', 'id_card_front_image', 'id_card_back_image']));

            // 2. Cập nhật thông tin bảng lease_members (Quan hệ, Ghi chú...)
            $member->update(\Illuminate\Support\Arr::only($data, ['relationship', 'move_in_date', 'note']));
        });

        return (new LeaseMemberResource($member->fresh('tenant')))
            ->additional(['message' => 'Cập nhật thông tin thành viên thành công.'])
            ->response();
    }

    /**
     * Xóa/Báo rời phòng cho thành viên
     */
    public function destroy(Request $request, int $tenantId): JsonResponse
    {
        $leaseIdHeader = $request->header('X-Lease-Id');
        $lease = $this->getActiveLease($request->user()->id, $leaseIdHeader ? (int)$leaseIdHeader : null);

        $member = LeaseMember::where('lease_id', $lease->id)
            ->where('tenant_id', $tenantId)
            ->whereNull('move_out_date')
            ->firstOrFail();

        // Chủ trọ ID để thực hiện hàm markTenantLeft
        $ownerId = $lease->room->property->user_id;

        // Tái sử dụng hàm rời phòng để đồng bộ trạng thái status='left'
        $this->tenantService->markTenantLeft($member->tenant, $ownerId, now()->toDateString());

        return response()->json(['message' => 'Đã ghi nhận thành viên rời phòng.']);
    }
}
