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
}
