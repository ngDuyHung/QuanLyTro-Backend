<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\Domain\BusinessException;
use App\Http\Controllers\Controller;
use App\Http\Requests\LeaseMember\StoreLeaseMemberRequest;
use App\Http\Requests\LeaseMember\UpdateLeaseMemberRequest;
use App\Http\Resources\LeaseMember\LeaseMemberResource;
use App\Models\Lease;
use App\Models\LeaseMember;
use App\Models\Tenant;
use App\Services\TenantService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class LeaseMemberController extends Controller
{
    public function __construct(
        private readonly TenantService $tenantService
    ) {}

    /**
     * Lấy danh sách thành viên ở cùng trong hợp đồng.
     * Ownership check: qua hợp đồng -> phòng -> khu nhà.
     */
    public function index(Request $request, int $leaseId): JsonResponse
    {
        $lease = Lease::whereHas('room.property', fn($q) => $q->where('user_id', $request->user()->id))
            ->findOrFail($leaseId);

        $members = $lease->members()
            ->with('tenant:id,full_name,phone,email,id_card_number')
            ->get();

        return LeaseMemberResource::collection($members)->response();
    }

    public function store(StoreLeaseMemberRequest $request, int $leaseId): JsonResponse
    {
        $lease = Lease::with(['room', 'members'])
            ->whereHas('room.property', fn($q) => $q->where('user_id', $request->user()->id))
            ->findOrFail($leaseId);

        if (!$lease->status->isActive()) {
            throw new BusinessException('Chỉ có thể thêm thành viên vào hợp đồng đang có hiệu lực.');
        }

        return DB::transaction(function () use ($request, $lease): JsonResponse {
            /*
        |--------------------------------------------------------------------------
        | 1. Kiểm tra sức chứa trước khi tạo tenant mới
        |--------------------------------------------------------------------------
        | currentCount = 1 người đại diện + số người ở ghép hiện tại
        | Nếu currentCount >= max_occupants thì không cho thêm nữa.
        */
            if ((int) $lease->room->max_occupants > 0) {
                $currentCount = $lease->members()->count() + 1;

                if ($currentCount >= (int) $lease->room->max_occupants) {
                    throw new BusinessException(
                        "Phòng này chỉ chứa tối đa {$lease->room->max_occupants} người. Hiện đang có {$currentCount} người."
                    );
                }
            }


            //2. Lấy tenant có sẵn hoặc tạo tenant mới
            if ($request->filled('tenant_id')) {
                $tenant = Tenant::findOrFail($request->integer('tenant_id'));
            } else {
                $tenant = $this->tenantService->createProfile($request->input('tenant'));
            }

            //3. Không cho thêm chính người đại diện vào danh sách ở ghép
            if ((int) $tenant->id === (int) $lease->tenant_id) {
                throw new BusinessException('Khách thuê này đã là người đứng tên hợp đồng, không thể thêm vào danh sách thành viên.');
            }

            //4. Không cho trùng thành viên trong cùng hợp đồng

            if ($lease->members()->where('tenant_id', $tenant->id)->exists()) {
                throw new BusinessException('Khách thuê này đã có trong danh sách thành viên của hợp đồng.');
            }

            //5. Tạo cư trú + lease_members bằng TenantService
            $this->tenantService->createMemberResidence(
                tenant: $tenant,
                lease: $lease,
                data: [
                    'relationship' => $request->input('relationship'),
                    'note' => $request->input('note'),
                    'move_in_date' => $request->input('move_in_date'),
                ]
            );

            $member = LeaseMember::with('tenant')
                ->where('lease_id', $lease->id)
                ->where('tenant_id', $tenant->id)
                ->firstOrFail();

            return (new LeaseMemberResource($member))
                ->response()
                ->setStatusCode(201);
        });
    }

    /**
     * Cập nhật thông tin thành viên (quan hệ, ghi chú, ngày chuyển vào/ra).
     * Ownership check: qua lease_member -> hợp đồng -> phòng -> khu nhà.
     */
    public function update(UpdateLeaseMemberRequest $request, int $id): JsonResponse
    {
        $member = LeaseMember::whereHas(
            'lease.room.property',
            fn($q) => $q->where('user_id', $request->user()->id)
        )->findOrFail($id);

        $member->update($request->validated());

        return (new LeaseMemberResource($member->load('tenant')))->response();
    }

    /**
     * Xóa thành viên khỏi hợp đồng.
     * Lưu ý: chỉ xóa bản ghi lease_member, KHÔNG xóa khách thuê trong bảng tenants.
     * Ownership check: qua lease_member -> hợp đồng -> phòng -> khu nhà.
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        $member = LeaseMember::whereHas(
            'lease.room.property',
            fn($q) => $q->where('user_id', $request->user()->id)
        )->findOrFail($id);

        $member->delete();

        return response()->json(['message' => 'Đã xóa thành viên khỏi hợp đồng.']);
    }
}
