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
     * Phương thức: GET /api/v1/dashboard
     */
    public function index(Request $request): JsonResponse
    {
        // Lấy dữ liệu từ service dựa trên ID của chủ trọ đang đăng nhập
        $data = $this->dashboardService->getDashboardData($request->user()->id);

        return response()->json([
            'success' => true,
            'data' => $data,
            
        ]);
    }

    /**
     * API: Lấy dữ liệu thống kê tổng hợp cho màn hình Dashboard Khách thuê
     * Phương thức: GET /api/v1/tenant/dashboard-summary
     */
    public function tenantIndex(Request $request): JsonResponse
    {
        // Lấy ID của user đang đăng nhập (khách thuê)
        $userId = $request->user()->id;

        $data = $this->dashboardService->getTenantDashboardData($userId);

        // Xử lý trường hợp user chưa có thông tin tenant hoặc hợp đồng
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
