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
    private function getActiveLease(int $userId, ?int $leaseId = null): Lease
    {
        $query = Lease::with(['room:id,property_id'])
            ->where('status', 'active')
            ->where(function (Builder $q) use ($userId) {
                $q->whereHas('tenant', fn($t) => $t->where('user_id', $userId))
                  ->orWhereHas('members.tenant', fn($t) => $t->where('user_id', $userId));
            });

        if ($leaseId) {
            $query->where('id', $leaseId);
        }

        $lease = $query->first();

        if (!$lease) {
            throw new BusinessException('Bạn chưa có hợp đồng thuê phòng nào đang hoạt động để thực hiện thao tác này.');
        }

        return $lease;
    }

    private function getCurrentTenant(int $userId): ?Tenant
    {
        return Tenant::where('user_id', $userId)->first();
    }

    public function getTenantIncidents(int $userId, array $filters = [], int $perPage = 15, ?int $leaseId = null): LengthAwarePaginator
    {
        $lease = $this->getActiveLease($userId, $leaseId); // Truyền $leaseId xuống

        $query = Incident::with(['property:id,name', 'room:id,name', 'images'])
            ->where('room_id', $lease->room_id);

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        return $query->latest()->paginate($perPage);
    }

    public function getTenantIncident(int $incidentId, int $userId, ?int $leaseId = null): Incident
    {
        $lease = $this->getActiveLease($userId, $leaseId); // Truyền $leaseId xuống

        return Incident::with(['property', 'room', 'reportedByTenant', 'images'])
            ->where('room_id', $lease->room_id)
            ->findOrFail($incidentId);
    }

    public function createIncident(array $data, array $images, int $userId, ?int $leaseId = null): Incident
    {
        $lease = $this->getActiveLease($userId, $leaseId); // Truyền $leaseId xuống
        $tenant = $this->getCurrentTenant($userId);

        $data['property_id'] = $lease->room->property_id;
        $data['room_id'] = $lease->room_id;
        $data['reported_by_tenant_id'] = $tenant?->id;

        return $this->incidentService->createIncident($data, $images, $userId);
    }

    public function updateIncident(int $incidentId, array $data, int $userId, ?int $leaseId = null): Incident
    {
        $incident = $this->getTenantIncident($incidentId, $userId, $leaseId);
        return $this->incidentService->updateIncident($incident, $data);
    }

    public function cancelIncident(int $incidentId, int $userId, ?int $leaseId = null): Incident
    {
        $incident = $this->getTenantIncident($incidentId, $userId, $leaseId);
        return $this->incidentService->cancelIncident($incident);
    }
}