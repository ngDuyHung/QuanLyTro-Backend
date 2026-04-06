<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\Domain\BusinessException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Lease\StoreLeaseRequest;
use App\Http\Requests\Lease\UpdateLeaseRequest;
use App\Http\Resources\Lease\LeaseResource;
use App\Models\Lease;
use App\Models\Tenant;
use App\Services\LeaseService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LeaseController extends Controller
{
    public function __construct(
        private readonly LeaseService $leaseService
    ) {}

    /**
     * Lấy danh sách hợp đồng thuê (filter: room_id, tenant_id, status, property_id).
     * Ownership check: qua phòng -> khu nhà.
     */
    public function index(Request $request): JsonResponse
    {
        $leases = Lease::with(['room:id,name,property_id', 'room.property:id,name', 'tenant:id,full_name,phone'])
            ->whereHas('room.property', fn ($q) => $q->where('user_id', $request->user()->id))
            ->when($request->room_id,     fn ($q) => $q->where('room_id', $request->room_id))
            ->when($request->tenant_id,   fn ($q) => $q->where('tenant_id', $request->tenant_id))
            ->when($request->status,      fn ($q) => $q->where('status', $request->status))
            ->when($request->property_id, fn ($q) => $q->whereHas('room', fn ($r) => $r->where('property_id', $request->property_id)))
            ->latest()
            ->paginate($request->integer('per_page', 15));

        return LeaseResource::collection($leases)->response();
    }

    /**
     * Xem chi tiết hợp đồng (kèm khách thuê, phòng, thành viên, hóa đơn).
     * Ownership check: qua phòng -> khu nhà.
     */
    public function show(Request $request, int $id): JsonResponse
    {
        $lease = Lease::with([
                'room.property:id,name,address',
                'tenant',
                'members',
                'invoices:id,lease_id,invoice_code,status,total_amount',
            ])
            ->whereHas('room.property', fn ($q) => $q->where('user_id', $request->user()->id))
            ->findOrFail($id);

        return (new LeaseResource($lease))->response();
    }

    /**
     * Tạo hợp đồng thuê mới (nhận phòng).
     * Nghiệp vụ phức tạp → giao cho LeaseService.
     */
    public function store(StoreLeaseRequest $request): JsonResponse
    {
        $lease = $this->leaseService->createLease(
            $request->validated(),
            $request->user()->id
        );

        return (new LeaseResource($lease))->response()->setStatusCode(201);
    }

    /**
     * Cập nhật hợp đồng (chỉ các trường không ảnh hưởng đến trạng thái).
     * Ownership check: qua phòng -> khu nhà.
     */
    public function update(UpdateLeaseRequest $request, int $id): JsonResponse
    {
        $lease = Lease::whereHas('room.property', fn ($q) => $q->where('user_id', $request->user()->id))
            ->findOrFail($id);

        // Chỉ cập nhật hợp đồng đang active
        if (!$lease->status->isActive()) {
            throw new BusinessException('Chỉ có thể cập nhật hợp đồng đang có hiệu lực.');
        }

        $lease->update($request->validated());

        return (new LeaseResource($lease->load(['room.property', 'tenant'])))->response();
    }

    /**
     * Kết thúc hợp đồng - trả phòng.
     * Ownership check: qua phòng -> khu nhà.
     */
    public function end(Request $request, int $id): JsonResponse
    {
        $lease = Lease::with('room')
            ->whereHas('room.property', fn ($q) => $q->where('user_id', $request->user()->id))
            ->findOrFail($id);

        $lease = $this->leaseService->endLease($lease);

        return (new LeaseResource($lease))->response();
    }

    /**
     * Xóa hợp đồng (chỉ khi chưa có hóa đơn nào).
     * Ownership check: qua phòng -> khu nhà.
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        $lease = Lease::with('room')
            ->whereHas('room.property', fn ($q) => $q->where('user_id', $request->user()->id))
            ->findOrFail($id);

        // Kiểm tra chưa có hóa đơn nào được tạo
        if ($lease->invoices()->exists()) {
            throw new BusinessException('Không thể xóa hợp đồng đã có hóa đơn.');
        }

        // Hoàn trả trạng thái phòng về available nếu hợp đồng đang active
        if ($lease->status->isActive()) {
            $lease->room->update(['status' => \App\Enums\RoomStatus::Available->value]);
        }

        // Xóa chỉ số điện nước ban đầu đi kèm (cascade không áp dụng vì invoice_id = null)
        $lease->meterReadings()->delete();

        $lease->delete();

        return response()->json(['message' => 'Xóa hợp đồng thành công.']);
    }

    /**
     * Đổi người đứng tên hợp đồng (đại diện).
     * Logic: chỉ cần cập nhật leases.tenant_id sang người mới.
     *   - Người mới phải là thành viên hiện tại trong lease_members.
     *   - Sau khi đổi, tự động xóa người mới khỏi lease_members (vì họ là đại diện rồi).
     * Ownership check: qua phòng -> khu nhà.
     */
    public function changeRepresentative(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'tenant_id' => ['required', 'integer', 'exists:tenants,id'],
        ], [
            'tenant_id.required' => 'Vui lòng chọn người đứng tên mới.',
            'tenant_id.exists'   => 'Khách thuê không tồn tại trong hệ thống.',
        ]);

        $lease = Lease::with('members')
            ->whereHas('room.property', fn ($q) => $q->where('user_id', $request->user()->id))
            ->findOrFail($id);

        // Chỉ đổi khi hợp đồng đang active
        if (!$lease->status->isActive()) {
            throw new BusinessException('Chỉ có thể đổi người đứng tên cho hợp đồng đang có hiệu lực.');
        }

        $newTenantId = $request->integer('tenant_id');

        // Không đổi nếu đã là người đứng tên hiện tại
        if ($newTenantId === $lease->tenant_id) {
            throw new BusinessException('Khách thuê này đã là người đứng tên hợp đồng hiện tại.');
        }

        // Người mới phải đang là thành viên trong lease_members
        $memberRecord = $lease->members()->where('tenant_id', $newTenantId)->first();
        if (!$memberRecord) {
            throw new BusinessException('Người được chọn phải là thành viên hiện tại trong hợp đồng mới có thể trở thành người đứng tên.');
        }

        // Cập nhật tenant_id trên hợp đồng & xóa thành viên đó khỏi lease_members
        $lease->update(['tenant_id' => $newTenantId]);
        $memberRecord->delete();

        return (new LeaseResource($lease->load(['room.property', 'tenant'])))->response();
    }
}
