<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\Domain\BusinessException;
use App\Models\Incident;
use App\Models\Lease;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;

class TenantIncidentService
{
    public function __construct(
        private readonly IncidentService $incidentService
    ) {}

    /**
     * Tự động lấy Hợp đồng đang hoạt động (Đại diện hoặc Ở ghép)
     */
    private function getActiveLease(int $userId): Lease
    {
        $lease = Lease::with(['room:id,property_id'])
            ->where(function (Builder $query) use ($userId) {
                $query->whereHas('tenant', fn($q) => $q->where('user_id', $userId))
                      ->orWhereHas('members.tenant', fn($q) => $q->where('user_id', $userId));
            })
            ->where('status', 'active')
            ->first();

        if (!$lease) {
            throw new BusinessException('Bạn chưa có hợp đồng thuê phòng nào đang hoạt động để báo cáo sự cố.');
        }

        return $lease;
    }

    /**
     * Lấy Profile Khách thuê hiện tại để lưu người báo cáo
     */
    private function getCurrentTenant(int $userId): ?Tenant
    {
        return Tenant::where('user_id', $userId)->first();
    }

    /**
     * Lấy danh sách sự cố của phòng đang ở
     */
    public function getTenantIncidents(int $userId, array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $lease = $this->getActiveLease($userId);

        $query = Incident::with(['property:id,name', 'room:id,name', 'images'])
            ->where('room_id', $lease->room_id);

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        return $query->latest()->paginate($perPage);
    }

    /**
     * Lấy chi tiết một sự cố thuộc phòng đang ở
     */
    public function getTenantIncident(int $incidentId, int $userId): Incident
    {
        $lease = $this->getActiveLease($userId);

        return Incident::with(['property', 'room', 'reportedByTenant', 'images'])
            ->where('room_id', $lease->room_id)
            ->findOrFail($incidentId);
    }

    /**
     * Tạo sự cố (Tự động gán phòng và khu nhà)
     */
    public function createIncident(array $data, array $images, int $userId): Incident
    {
        $lease = $this->getActiveLease($userId);
        $tenant = $this->getCurrentTenant($userId);

        // Bơm ngầm dữ liệu bảo mật vào mảng
        $data['property_id'] = $lease->room->property_id;
        $data['room_id'] = $lease->room_id;
        $data['reported_by_tenant_id'] = $tenant?->id;

        // Gọi lõi của Chủ trọ để lưu DB & File
        return $this->incidentService->createIncident($data, $images, $userId);
    }

    /**
     * Cập nhật sự cố (Mượn lõi của Chủ trọ, lõi đã tự check phải là trạng thái Pending mới cho sửa)
     */
    public function updateIncident(int $incidentId, array $data, int $userId): Incident
    {
        $incident = $this->getTenantIncident($incidentId, $userId);
        return $this->incidentService->updateIncident($incident, $data);
    }

    /**
     * Hủy sự cố (Mượn lõi của Chủ trọ)
     */
    public function cancelIncident(int $incidentId, int $userId): Incident
    {
        $incident = $this->getTenantIncident($incidentId, $userId);
        return $this->incidentService->cancelIncident($incident);
    }
}