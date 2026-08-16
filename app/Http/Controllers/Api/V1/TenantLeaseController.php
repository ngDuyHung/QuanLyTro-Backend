<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Lease\LeaseResource;
use App\Services\SettingService;
use App\Services\TenantLeaseService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TenantLeaseController extends Controller
{
    public function __construct(
        private readonly TenantLeaseService $tenantLeaseService
    ) {}

    /**
     * Lấy danh sách hợp đồng (GIỮ NGUYÊN KHÔNG TRUYỀN LEASE ID)
     * Vì màn hình này cần hiển thị tất cả các hợp đồng của người dùng
     */
    public function index(Request $request): JsonResponse
    {
        $filters = $request->only(['status']);

        $leases = $this->tenantLeaseService->getTenantLeases(
            userId: $request->user()->id,
            filters: $filters,
            perPage: $request->integer('per_page', 15)
        );

        return LeaseResource::collection($leases)->response();
    }

    /**
     * Xem chi tiết hợp đồng
     */
    public function show(Request $request, int $id): JsonResponse
    {
        // Ở đây chúng ta vẫn dùng $id do Frontend truyền lên URL (VD: /leases/5)
        $lease = $this->tenantLeaseService->getTenantLease(
            leaseId: $id,
            userId: $request->user()->id
        );

        return (new LeaseResource($lease))->response();
    }

    /**
     * Lấy bản in HTML của hợp đồng để người thuê đọc/in
     */
    public function previewHtml(Request $request, int $id, SettingService $settingService): JsonResponse
    {
        $lease = $this->tenantLeaseService->getTenantLease(
            leaseId: $id,
            userId: $request->user()->id
        );

        $landlordId = (int) $lease->room->property->user_id;

        $html = $settingService->compileLeaseHtml($lease->id, $landlordId);

        return response()->json(['html' => $html]);
    }

    /**
     * Đăng ký trả phòng
     */
    public function registerCheckout(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'move_out_date' => ['required', 'date', 'date_format:Y-m-d'],
        ], [
            'move_out_date.required' => 'Vui lòng chọn ngày dự kiến trả phòng.',
            'move_out_date.date' => 'Định dạng ngày không hợp lệ.',
            'move_out_date.date_format' => 'Định dạng ngày phải là YYYY-MM-DD.',
        ]);

        $lease = $this->tenantLeaseService->registerCheckoutNotice(
            $id,
            $request->move_out_date,
            $request->user()->id
        );

        return response()->json([
            'message' => 'Đăng ký trả phòng thành công.',
            'data' => new LeaseResource($lease)
        ]);
    }
}