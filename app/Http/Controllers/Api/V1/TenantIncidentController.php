<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Incident\TenantStoreIncidentRequest;
use App\Http\Resources\Incident\IncidentResource;
use App\Services\TenantIncidentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TenantIncidentController extends Controller
{
    public function __construct(
        private readonly TenantIncidentService $tenantIncidentService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $filters = $request->only(['status']);

        $incidents = $this->tenantIncidentService->getTenantIncidents(
            userId: $request->user()->id,
            filters: $filters,
            perPage: $request->integer('per_page', 15)
        );

        return IncidentResource::collection($incidents)->response();
    }

    public function store(TenantStoreIncidentRequest $request): JsonResponse
    {
        $incident = $this->tenantIncidentService->createIncident(
            data: $request->validated(),
            images: $request->file('images', []),
            userId: $request->user()->id
        );

        return (new IncidentResource($incident))
            ->additional(['message' => 'Báo cáo sự cố thành công. Vui lòng chờ phản hồi từ quản lý.'])
            ->response()
            ->setStatusCode(201);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $incident = $this->tenantIncidentService->getTenantIncident($id, $request->user()->id);
        return (new IncidentResource($incident))->response();
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'category' => ['required', 'string', 'in:electrical,water,furniture,security,other'],
            'priority' => ['required', 'string', 'in:low,normal,high,emergency'],
        ]);

        $incident = $this->tenantIncidentService->updateIncident($id, $data, $request->user()->id);

        return (new IncidentResource($incident))
            ->additional(['message' => 'Cập nhật sự cố thành công.'])
            ->response();
    }

    public function cancel(Request $request, int $id): JsonResponse
    {
        $incident = $this->tenantIncidentService->cancelIncident($id, $request->user()->id);

        return (new IncidentResource($incident))
            ->additional(['message' => 'Đã hủy báo cáo sự cố.'])
            ->response();
    }
}
