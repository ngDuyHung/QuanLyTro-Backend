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
     * Lấy thông tin hồ sơ của khách thuê đang đăng nhập
     */
    public function show(Request $request): JsonResponse
    {
        $tenant = Tenant::with(['user'])
            ->where('user_id', $request->user()->id)
            ->first();

        if (!$tenant) {
            return response()->json([
                'message' => 'Không tìm thấy hồ sơ lưu trú liên kết với tài khoản này.'
            ], 404);
        }

        return (new TenantResource($tenant))->response();
    }
}