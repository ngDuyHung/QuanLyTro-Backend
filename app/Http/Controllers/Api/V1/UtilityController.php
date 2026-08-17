<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Utility\StoreUtilityRequest;
use App\Http\Requests\Utility\UpdateUtilityRequest;
use App\Http\Resources\Utility\UtilityResource;
use App\Models\MeterReading;
use App\Models\Room;
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
     * 
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        // TỐI ƯU 1: Lấy danh sách hợp đồng thuộc sở hữu của User (1 Query duy nhất)
        $userLeaseIds = \App\Models\Lease::whereHas('room.property', function ($q) use ($user) {
            $q->where('user_id', $user->id);
        })->pluck('id');

        $readings = MeterReading::with([
            'lease.room.property',
            'lease.tenant:id,full_name'
        ])
            // TỐI ƯU 2: Thay thế whereHas 3 tầng bằng whereIn
            ->whereIn('lease_id', $userLeaseIds)

            ->when($request->lease_id, fn($q) => $q->where('lease_id', $request->lease_id))

            ->when($request->property_id, function ($q, $propertyId) {
                // TỐI ƯU 3: Lọc theo property_id mà không dùng whereHas lồng nhau trong MeterReading
                $propertyLeaseIds = \App\Models\Lease::whereHas('room', fn($r) => $r->where('property_id', $propertyId))->pluck('id');
                $q->whereIn('lease_id', $propertyLeaseIds);
            })
            ->when($request->room_id, function ($q, $roomId) {
                // TỐI ƯU 4: Lọc theo room_id trực tiếp qua Lease
                $roomLeaseIds = \App\Models\Lease::where('room_id', $roomId)->pluck('id');
                $q->whereIn('lease_id', $roomLeaseIds);
            })

            ->when($request->type, fn($q) => $q->where('type', $request->type))
            ->when($request->month, function ($q) use ($request) {
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

    /**
     * API Phân tích tiêu thụ 6 tháng gần nhất (Phục vụ vẽ biểu đồ).
     */
    public function analysis(Request $request): JsonResponse
    {
        $request->validate([
            'room_id' => ['required', 'integer', 'exists:rooms,id'],
            'type' => ['required', 'in:electricity,water'],
        ]);

        // 1. Kiểm tra Ownership: Khu nhà của phòng này có thuộc chủ trọ đang đăng nhập không?
        $room = Room::whereHas('property', fn($q) => $q->where('user_id', $request->user()->id))
            ->findOrFail($request->room_id);

        // 2. Chuyển logic tính toán xuống Service
        $data = $this->utilityService->analyze6Months($room->id, $request->type);

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }
}
