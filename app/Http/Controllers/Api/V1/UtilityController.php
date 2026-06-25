<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Utility\StoreUtilityRequest;
use App\Http\Requests\Utility\UpdateUtilityRequest;
use App\Http\Resources\Utility\UtilityResource;
use App\Models\MeterReading;
use App\Services\UtilityService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UtilityController extends Controller
{
    public function __construct(
        private readonly UtilityService $utilityService
    ) {}

    /**
     * Lấy danh sách chỉ số tiện ích (điện, nước).
     * Ownership check: qua hợp đồng -> phòng -> khu nhà.
     */
    public function index(Request $request): JsonResponse
    {
        $readings = MeterReading::with([
            'lease.room.property', 
            'lease.tenant:id,full_name'
        ])
            ->whereHas('lease.room.property', fn($q) => $q->where('user_id', $request->user()->id))
            ->when($request->property_id, fn($q) => $q->whereHas('lease.room', fn($r) => $r->where('property_id', $request->property_id)))
            ->when($request->room_id, fn($q) => $q->whereHas('lease', fn($l) => $l->where('room_id', $request->room_id)))
            ->when($request->type, fn($q) => $q->where('type', $request->type))
            ->when($request->month, function ($q) use ($request) {
                // Filter theo tháng (Định dạng YYYY-MM)
                $q->whereMonth('reading_date', substr((string)$request->month, 5, 2))
                  ->whereYear('reading_date', substr((string)$request->month, 0, 4));
            })
            ->latest('reading_date')
            ->latest('id')
            ->paginate($request->integer('per_page', 15));

        return UtilityResource::collection($readings)->response();
    }

    /**
     * Lấy chi tiết 1 chỉ số.
     */
    public function show(Request $request, int $id): JsonResponse
    {
        $reading = MeterReading::with(['lease.room.property', 'lease.tenant'])
            ->whereHas('lease.room.property', fn($q) => $q->where('user_id', $request->user()->id))
            ->findOrFail($id);

        return (new UtilityResource($reading))->response();
    }

    /**
     * Thêm mới chỉ số (điện/nước).
     */
    public function store(StoreUtilityRequest $request): JsonResponse
    {
        // Kiểm tra Lease có thuộc quyền sở hữu không
        $isOwner = \App\Models\Lease::where('id', $request->lease_id)
            ->whereHas('room.property', fn($q) => $q->where('user_id', $request->user()->id))
            ->exists();

        if (!$isOwner) abort(403, 'Bạn không có quyền thao tác trên hợp đồng này.');

        $reading = $this->utilityService->createReading($request->validated());

        return (new UtilityResource($reading->load(['lease.room.property', 'lease.tenant'])))
            ->additional(['message' => 'Ghi nhận chỉ số thành công.'])
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Cập nhật chỉ số.
     */
    public function update(UpdateUtilityRequest $request, int $id): JsonResponse
    {
        $reading = MeterReading::whereHas('lease.room.property', fn($q) => $q->where('user_id', $request->user()->id))
            ->findOrFail($id);

        $reading = $this->utilityService->updateReading($reading, $request->validated());

        return (new UtilityResource($reading->load(['lease.room.property', 'lease.tenant'])))
            ->additional(['message' => 'Cập nhật chỉ số thành công.'])
            ->response();
    }

    /**
     * Xóa chỉ số.
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        $reading = MeterReading::whereHas('lease.room.property', fn($q) => $q->where('user_id', $request->user()->id))
            ->findOrFail($id);

        $this->utilityService->deleteReading($reading);

        return response()->json(['message' => 'Đã xóa chỉ số thành công.']);
    }
}