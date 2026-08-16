<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Notification\NotificationResource;
use App\Services\TenantNotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TenantNotificationController extends Controller
{
    public function __construct(
        private readonly TenantNotificationService $tenantNotificationService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $leaseIdHeader = $request->header('X-Lease-Id');
        $leaseId = $leaseIdHeader ? (int) $leaseIdHeader : null;

        $notifications = $this->tenantNotificationService->getTenantNotifications(
            userId: $request->user()->id,
            perPage: $request->integer('per_page', 15),
            leaseId: $leaseId // Truyền thêm leaseId
        );

        return NotificationResource::collection($notifications)->response();
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $leaseIdHeader = $request->header('X-Lease-Id');
        $leaseId = $leaseIdHeader ? (int) $leaseIdHeader : null;

        $notification = $this->tenantNotificationService->getNotificationDetail(
            id: $id,
            userId: $request->user()->id,
            leaseId: $leaseId // Truyền thêm leaseId
        );

        return (new NotificationResource($notification))->response();
    }
}