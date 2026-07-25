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
        
        $readings = $this->tenantUtilityService->getTenantReadings(
            userId: $request->user()->id,
            filters: $filters,
            perPage: $request->integer('per_page', 15)
        );

        return UtilityResource::collection($readings)->response();
    }

    public function currentReadings(Request $request): JsonResponse
    {
        $data = $this->tenantUtilityService->getCurrentReadings($request->user()->id);
        
        return response()->json(['data' => $data]);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $reading = $this->tenantUtilityService->getReadingDetail($id, $request->user()->id);
        
        return (new UtilityResource($reading))->response();
    }

    public function submitBatch(Request $request): JsonResponse
    {
        $data = $request->validate([
            'reading_date' => ['required', 'date'],
            'electricity_reading' => ['nullable', 'numeric', 'min:0'],
            'water_reading' => ['nullable', 'numeric', 'min:0'],
            'electricity_image' => ['nullable', 'image', 'mimes:jpeg,png,jpg,webp', 'max:5120'], // max 5MB
            'water_image' => ['nullable', 'image', 'mimes:jpeg,png,jpg,webp', 'max:5120'],
            'note' => ['nullable', 'string', 'max:255'],
        ]);

        if (empty($data['electricity_reading']) && empty($data['water_reading'])) {
            throw new BusinessException('Vui lòng nhập ít nhất một chỉ số (điện hoặc nước).');
        }

        $this->tenantUtilityService->submitBatch(
            userId: $request->user()->id,
            data: $data,
            elecImage: $request->file('electricity_image'),
            waterImage: $request->file('water_image')
        );

        return response()->json([
            'message' => 'Đã gửi chỉ số tiêu thụ thành công. Cảm ơn bạn!'
        ]);
    }
}