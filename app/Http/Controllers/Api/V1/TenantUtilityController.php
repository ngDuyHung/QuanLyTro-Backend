<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Utility\UtilityResource;
use App\Services\TenantUtilityService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Exceptions\Domain\BusinessException;

class TenantUtilityController extends Controller
{
    public function __construct(
        private readonly TenantUtilityService $tenantUtilityService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $filters = $request->only(['type', 'month']);
        $leaseId = $request->header('X-Lease-Id') ? (int) $request->header('X-Lease-Id') : null; // ĐỌC HEADER
        
        $readings = $this->tenantUtilityService->getTenantReadings(
            userId: $request->user()->id,
            filters: $filters,
            perPage: $request->integer('per_page', 15),
            leaseId: $leaseId // TRUYỀN XUỐNG SERVICE
        );

        return UtilityResource::collection($readings)->response();
    }

    public function currentReadings(Request $request): JsonResponse
    {
        $leaseId = $request->header('X-Lease-Id') ? (int) $request->header('X-Lease-Id') : null;
        $data = $this->tenantUtilityService->getCurrentReadings($request->user()->id, $leaseId);
        
        return response()->json(['data' => $data]);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $leaseId = $request->header('X-Lease-Id') ? (int) $request->header('X-Lease-Id') : null;
        $reading = $this->tenantUtilityService->getReadingDetail($id, $request->user()->id, $leaseId);
        
        return (new UtilityResource($reading))->response();
    }

    public function submitBatch(Request $request): JsonResponse
    {
        $data = $request->validate([
            'reading_date' => ['required', 'date'],
            'electricity_reading' => ['nullable', 'numeric', 'min:0'],
            'water_reading' => ['nullable', 'numeric', 'min:0'],
            'electricity_image' => ['nullable', 'image', 'mimes:jpeg,png,jpg,webp', 'max:5120'],
            'water_image' => ['nullable', 'image', 'mimes:jpeg,png,jpg,webp', 'max:5120'],
            'note' => ['nullable', 'string', 'max:255'],
        ]);

        if (empty($data['electricity_reading']) && empty($data['water_reading'])) {
            throw new BusinessException('Vui lòng nhập ít nhất một chỉ số (điện hoặc nước).');
        }

        $leaseId = $request->header('X-Lease-Id') ? (int) $request->header('X-Lease-Id') : null;

        $this->tenantUtilityService->submitBatch(
            userId: $request->user()->id,
            data: $data,
            elecImage: $request->file('electricity_image'),
            waterImage: $request->file('water_image'),
            leaseId: $leaseId // TRUYỀN XUỐNG SERVICE
        );

        return response()->json([
            'message' => 'Đã gửi chỉ số tiêu thụ thành công. Cảm ơn bạn!'
        ]);
    }
}