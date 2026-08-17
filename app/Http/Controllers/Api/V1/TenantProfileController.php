<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Tenant\TenantResource;
use App\Models\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TenantProfileController extends Controller
{
    /**
     * Lấy thông tin hồ sơ của khách thuê đang đăng nhập dựa theo hợp đồng đang chọn
     */
    public function show(Request $request): JsonResponse
    {
        $userId = $request->user()->id;
        $leaseIdHeader = $request->header('X-Lease-Id');

        // TỐI ƯU: Truy xuất ID trực tiếp thay vì dùng whereHas lồng nhau
        $tenantIds = \App\Models\Tenant::where('user_id', $userId)->pluck('id')->toArray();
        $memberLeaseIds = \App\Models\LeaseMember::whereIn('tenant_id', $tenantIds)->pluck('lease_id')->toArray();

        // 1. Tìm Hợp đồng để suy ra Chủ trọ (owner_id)
        $query = \App\Models\Lease::with('room.property')
            ->where('status', 'active')
            ->where(function ($q) use ($tenantIds, $memberLeaseIds) {
                $q->whereIn('tenant_id', $tenantIds)
                    ->orWhereIn('id', $memberLeaseIds);
            });

        if ($leaseIdHeader) {
            $query->where('id', $leaseIdHeader);
        }

        $lease = $query->first();

        if (!$lease) {
            return response()->json([
                'message' => 'Không tìm thấy hợp đồng lưu trú đang hoạt động.'
            ], 404);
        }

        $ownerId = $lease->room->property->user_id;

        // 2. Lấy ĐÚNG hồ sơ tenant liên kết với tài khoản này và thuộc về chủ trọ đang xét
        $tenant = Tenant::with(['user'])
            ->where('user_id', $userId)
            ->where('owner_id', $ownerId)
            ->first();

        if (!$tenant) {
            return response()->json([
                'message' => 'Không tìm thấy hồ sơ lưu trú liên kết với tài khoản này.'
            ], 404);
        }

        return (new TenantResource($tenant))->response();
    }
}
