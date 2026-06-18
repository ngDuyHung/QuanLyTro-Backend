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
        $lease = Lease::whereHas('room.property', fn ($q) => $q->where('user_id', $request->user()->id))
            ->findOrFail($leaseId);

        $members = $lease->members()
            ->with('tenant:id,full_name,phone,email,id_card_number')
            ->get();

        return LeaseMemberResource::collection($members)->response();
    }

    /**
     * Thêm thành viên ở cùng vào hợp đồng.
     * Hỗ trợ 2 cách:
     *   - Truyền tenant_id: liên kết với khách thuê đã có trong hệ thống.
     *   - Truyền object tenant: tạo khách thuê mới rồi liên kết.
     * Ownership check: qua hợp đồng -> phòng -> khu nhà.
     */
    public function store(StoreLeaseMemberRequest $request, int $leaseId): JsonResponse
    {
        $lease = Lease::with('room')
            ->whereHas('room.property', fn ($q) => $q->where('user_id', $request->user()->id))
            ->findOrFail($leaseId);

        // Chỉ cho phép thêm thành viên vào hợp đồng đang active
        if (!$lease->status->isActive()) {
            throw new BusinessException('Chỉ có thể thêm thành viên vào hợp đồng đang có hiệu lực.');
        }

        return DB::transaction(function () use ($request, $lease): JsonResponse {
            // Xác định tenant_id: dùng có sẵn hoặc tạo mới
            if ($request->filled('tenant_id')) {
                $tenantId = $request->integer('tenant_id');
            } else {
                $tenant = $this->tenantService->createTenant($request->input('tenant'), $request->user()->id);
                $tenantId = $tenant->id;
            }

            // Kiểm tra tenant không phải người đứng tên hợp đồng
            if ($tenantId === $lease->tenant_id) {
                throw new BusinessException('Khách thuê này đã là người đứng tên hợp đồng, không thể thêm vào danh sách thành viên.');
            }

            // Kiểm tra tenant chưa có trong danh sách thành viên của hợp đồng này
            if ($lease->members()->where('tenant_id', $tenantId)->exists()) {
                throw new BusinessException('Khách thuê này đã có trong danh sách thành viên của hợp đồng.');
            }

            // Kiểm tra sức chứa tối đa của phòng (nếu có giới hạn)
            if ($lease->room->max_occupants > 0) {
                // Đếm: người đứng tên (1) + số thành viên hiện tại
                $currentCount = $lease->members()->count() + 1;
                if ($currentCount >= $lease->room->max_occupants) {
                    throw new BusinessException(
                        "Phòng này chỉ chứa tối đa {$lease->room->max_occupants} người. Hiện đang có {$currentCount} người."
                    );
                }
            }

            $member = LeaseMember::create([
                'lease_id'     => $lease->id,
                'tenant_id'    => $tenantId,
                'relationship' => $request->input('relationship'),
                'note'         => $request->input('note'),
                'move_in_date' => $request->input('move_in_date'),
            ]);

            return (new LeaseMemberResource($member->load('tenant')))->response()->setStatusCode(201);
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
            fn ($q) => $q->where('user_id', $request->user()->id)
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
            fn ($q) => $q->where('user_id', $request->user()->id)
        )->findOrFail($id);

        $member->delete();

        return response()->json(['message' => 'Đã xóa thành viên khỏi hợp đồng.']);
    }
}
