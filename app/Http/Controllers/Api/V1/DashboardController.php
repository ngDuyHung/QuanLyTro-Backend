<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\DashboardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __construct(
        private readonly DashboardService $dashboardService
    ) {}

    /**
     * API: Lấy dữ liệu thống kê tổng hợp cho màn hình Dashboard
     */
    public function index(Request $request): JsonResponse
    {
        $data = $this->dashboardService->getDashboardData($request->user()->id);

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }

    /**
     * API: Lấy dữ liệu thống kê tổng hợp cho màn hình Dashboard Khách thuê
     */
    public function tenantIndex(Request $request): JsonResponse
    {
        $userId = $request->user()->id;
        
        // ĐỌC HEADER X-Lease-Id TỪ FRONTEND GỬI LÊN
        $leaseIdHeader = $request->header('X-Lease-Id');
        $leaseId = $leaseIdHeader ? (int) $leaseIdHeader : null;

        // TRUYỀN THÊM $leaseId VÀO SERVICE
        $data = $this->dashboardService->getTenantDashboardData($userId, $leaseId);

        if (isset($data['error'])) {
            return response()->json([
                'success' => false,
                'message' => $data['error']
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }
}