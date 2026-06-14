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
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Throwable;

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

        $rooms = Room::with(['images' => fn($query) => $query->orderBy('sort_order')])
            ->where('property_id', $property->id)
            ->when(
                $request->search,
                fn($q) => $q->where('name', 'like', "%{$request->search}%")
            )
            ->when(
                $request->status,
                fn($q) => $q->where('status', $request->status)
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
        $room = Room::with([
            'property',
            'images' => fn($query) => $query->orderBy('sort_order'),
        ])
            ->whereHas('property', fn($q) => $q->where('user_id', $request->user()->id))
            ->findOrFail($id);

        return new RoomResource($room);
    }

    /**
     * Tạo phòng mới trong khu nhà.
     * Tự động ghi room_price_histories cho lần đầu (nếu có giá).
     */
    public function store(StoreRoomRequest $request, int $propertyId): JsonResponse
    {
        $property = Property::where('user_id', $request->user()->id)->findOrFail($propertyId);

        $storedPaths = [];

        try {
            DB::beginTransaction();

            $data = $request->validated();

            $images = $request->file('images', []);
            $coverImageIndex = (int) ($data['cover_image_index'] ?? 0);

            if ($coverImageIndex < 0 || $coverImageIndex >= count($images)) {
                $coverImageIndex = 0;
            }

            unset($data['images'], $data['cover_image_index']);

            $data['property_id'] = $property->id;
            $data['status'] = $data['status'] ?? RoomStatus::Available->value;

            $room = Room::create($data);

            if ($room->current_price > 0) {
                RoomPriceHistory::create([
                    'room_id' => $room->id,
                    'user_id' => $request->user()->id,
                    'old_price' => 0,
                    'new_price' => $room->current_price,
                    'effective_date' => now()->toDateString(),
                    'note' => 'Giá khởi tạo khi tạo phòng.',
                ]);
            }

            foreach ($images as $index => $image) {
                $path = $image->store("rooms/{$room->id}", 'public');

                $storedPaths[] = $path;

                $room->images()->create([
                    'image_path' => $path,
                    'is_cover' => $index === $coverImageIndex,
                    'sort_order' => $index,
                ]);
            }

            DB::commit();

            $room->load('images');

            return (new RoomResource($room))->response()->setStatusCode(201);
        } catch (Throwable $exception) {
            DB::rollBack();

            foreach ($storedPaths as $path) {
                Storage::disk('public')->delete($path);
            }

            throw $exception;
        }
    }
    /**
     * Cập nhật thông tin phòng.
     * Nếu thay đổi current_price → tự động ghi room_price_histories.
     */
    public function update(UpdateRoomRequest $request, int $id): RoomResource
    {
        $room = Room::whereHas('property', fn($q) => $q->where('user_id', $request->user()->id))
            ->findOrFail($id);

        $validated   = $request->validated();
        $oldPrice    = $room->current_price;
        $newPrice    = $validated['current_price'] ?? null;

        $room->update($validated);

        // Tự động ghi lịch sử khi giá thay đổi
        if ($newPrice !== null && (int) $newPrice !== (int) $oldPrice) {
            RoomPriceHistory::create([
                'room_id' => $room->id,
                'user_id' => $request->user()->id,
                'old_price' => (int) $oldPrice,
                'new_price' => (int) $newPrice,
                'effective_date' => now()->toDateString(),
                'note' => $request->input('price_note'),
            ]);
        }

        $room->load(['images' => fn($query) => $query->orderBy('sort_order')]);
        return new RoomResource($room);
    }

    /**
     * Xóa phòng — chỉ được xóa khi status = available.
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        $room = Room::whereHas('property', fn($q) => $q->where('user_id', $request->user()->id))
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
        $room = Room::whereHas('property', fn($q) => $q->where('user_id', $request->user()->id))
            ->findOrFail($id);

        // Không cho chuyển status của phòng đang có hợp đồng active
        if ($room->status === RoomStatus::Occupied) {
            throw new BusinessException('Không thể thay đổi trạng thái phòng đang có hợp đồng thuê.');
        }

        $room->update(['status' => $request->status]);

        $room->load(['images' => fn($query) => $query->orderBy('sort_order')]);

        return new RoomResource($room);
    }
}
