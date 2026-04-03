<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Enums\RoomStatus;
use App\Exceptions\Domain\BusinessException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Room\StoreRoomRequest;
use App\Http\Requests\Room\UpdateRoomRequest;
use App\Http\Requests\Room\UpdateRoomStatusRequest;
use App\Http\Resources\Room\RoomResource;
use App\Models\Property;
use App\Models\Room;
use App\Models\RoomPriceHistory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RoomController extends Controller
{
    /**
     * Lấy danh sách phòng trong khu nhà (có filter status, search tên phòng).
     * Ownership check: khu nhà phải thuộc về user đang đăng nhập.
     */
    public function index(Request $request, int $propertyId): JsonResponse
    {
        // Kiểm tra khu nhà có thuộc chủ trọ này không
        $property = Property::where('user_id', $request->user()->id)->findOrFail($propertyId);

        $rooms = Room::where('property_id', $property->id)
            ->when(
                $request->search,
                fn ($q) => $q->where('name', 'like', "%{$request->search}%")
            )
            ->when(
                $request->status,
                fn ($q) => $q->where('status', $request->status)
            )
            ->latest()
            ->paginate($request->integer('per_page', 15));

        return RoomResource::collection($rooms)->response();
    }

    /**
     * Xem chi tiết một phòng.
     * Ownership check qua property.
     */
    public function show(Request $request, int $id): RoomResource
    {
        $room = Room::with('property')
            ->whereHas('property', fn ($q) => $q->where('user_id', $request->user()->id))
            ->findOrFail($id);

        return new RoomResource($room);
    }

    /**
     * Tạo phòng mới trong khu nhà.
     * Tự động ghi room_price_histories cho lần đầu (nếu có giá).
     */
    public function store(StoreRoomRequest $request, int $propertyId): JsonResponse
    {
        // Kiểm tra khu nhà có thuộc chủ trọ này không
        $property = Property::where('user_id', $request->user()->id)->findOrFail($propertyId);

        $data             = $request->validated();
        $data['property_id'] = $property->id;
        $data['status']      = RoomStatus::Available->value;

        $room = Room::create($data);

        // Ghi lịch sử giá ban đầu nếu giá > 0
        if ($room->current_price > 0) {
            RoomPriceHistory::create([
                'room_id'        => $room->id,
                'user_id'        => $request->user()->id,
                'old_price'      => 0,
                'new_price'      => $room->current_price,
                'effective_date' => now()->toDateString(),
                'note'           => 'Giá khởi tạo khi tạo phòng.',
            ]);
        }

        return (new RoomResource($room))->response()->setStatusCode(201);
    }

    /**
     * Cập nhật thông tin phòng.
     * Nếu thay đổi current_price → tự động ghi room_price_histories.
     */
    public function update(UpdateRoomRequest $request, int $id): RoomResource
    {
        $room = Room::whereHas('property', fn ($q) => $q->where('user_id', $request->user()->id))
            ->findOrFail($id);

        $validated   = $request->validated();
        $oldPrice    = $room->current_price;
        $newPrice    = $validated['current_price'] ?? null;

        $room->update($validated);

        // Tự động ghi lịch sử khi giá thay đổi
        if ($newPrice !== null && $newPrice !== $oldPrice) {
            RoomPriceHistory::create([
                'room_id'        => $room->id,
                'user_id'        => $request->user()->id,
                'old_price'      => $oldPrice,
                'new_price'      => $newPrice,
                'effective_date' => now()->toDateString(),
                'note'           => $request->input('price_note'),
            ]);
        }

        return new RoomResource($room);
    }

    /**
     * Xóa phòng — chỉ được xóa khi status = available.
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        $room = Room::whereHas('property', fn ($q) => $q->where('user_id', $request->user()->id))
            ->findOrFail($id);

        if ($room->status !== RoomStatus::Available) {
            throw new BusinessException('Chỉ có thể xóa phòng đang ở trạng thái trống.');
        }

        $room->delete();

        return response()->json(['message' => 'Xóa phòng thành công.']);
    }

    /**
     * Đổi trạng thái phòng (available <-> maintenance).
     * Trạng thái "occupied" chỉ được set tự động khi tạo hợp đồng,
     * không cho phép đổi thủ công sang occupied.
     */
    public function updateStatus(UpdateRoomStatusRequest $request, int $id): RoomResource
    {
        $room = Room::whereHas('property', fn ($q) => $q->where('user_id', $request->user()->id))
            ->findOrFail($id);

        // Không cho chuyển status của phòng đang có hợp đồng active
        if ($room->status === RoomStatus::Occupied) {
            throw new BusinessException('Không thể thay đổi trạng thái phòng đang có hợp đồng thuê.');
        }

        $room->update(['status' => $request->status]);

        return new RoomResource($room);
    }
}
