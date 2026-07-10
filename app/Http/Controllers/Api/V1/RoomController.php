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
use App\Services\RoomService;

class RoomController extends Controller
{
    public function __construct(
        private readonly RoomService $roomService
    ) {}

    /**
     * Lấy danh sách phòng trong khu nhà (có filter status, search tên phòng).
     * Ownership check: khu nhà phải thuộc về user đang đăng nhập.
     */
    public function index(Request $request, int $propertyId): JsonResponse
    {
        // Kiểm tra khu nhà có thuộc chủ trọ này không
        $property = Property::where('user_id', $request->user()->id)->findOrFail($propertyId);

        // Lấy danh sách phòng trong khu nhà, kèm ảnh và cư dân hiện tại
        $rooms = Room::with([
            'property',
            'images' => fn($query) => $query->orderBy('sort_order'),

            'currentResidents' => fn($query) => $query
                ->with('tenant:id,full_name,phone,email,id_card_number')
                ->orderByRaw("role = 'representative' desc")
                ->orderBy('id'),
            'reservations' => fn($query) => $query
                ->where('status', 'pending'),
            // Lấy các hóa đơn đang nợ để tính tổng tiền[cite: 2]
            'invoices' => fn($query) => $query->whereIn('status', ['issued', 'partially_paid', 'overdue']),
            // THÊM DÒNG NÀY: Lấy hóa đơn mới nhất để check xem tháng này đã lập chưa
            'latestInvoice' // funct trong model
        ])
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


    public function all(Request $request): JsonResponse
    {
        $rooms = Room::with([
            'property',
            'images' => fn($query) => $query->orderBy('sort_order'),

            // BỔ SUNG ĐOẠN NÀY ĐỂ EAGER LOAD NGƯỜI THUÊ (Fix lỗi N+1 và rỗng data)
            'currentResidents' => fn($query) => $query
                ->with('tenant:id,full_name,phone,email,id_card_number')
                ->orderByRaw("role = 'representative' desc")
                ->orderBy('id'),
            'reservations' => fn($query) => $query->where('status', 'pending'),
            'invoices' => fn($query) => $query->whereIn('status', ['issued', 'partially_paid', 'overdue']),
            'latestInvoice'
        ])
            ->whereHas(
                'property',
                fn($query) =>
                $query->where('user_id', $request->user()->id)
            )
            ->when($request->filled('property_id'), function ($query) use ($request) {
                $query->where('property_id', $request->integer('property_id'));
            })
            ->when($request->filled('status'), function ($query) use ($request) {
                $query->where('status', $request->status);
            })
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = trim((string) $request->search);

                $query->where(function ($subQuery) use ($search) {
                    $subQuery
                        ->where('name', 'like', "%{$search}%")
                        ->orWhereHas('property', function ($propertyQuery) use ($search) {
                            $propertyQuery
                                ->where('name', 'like', "%{$search}%")
                                ->orWhere('address', 'like', "%{$search}%");
                        });
                });
            })
            ->when($request->filled('sort'), function ($query) use ($request) {
                match ($request->sort) {
                    'price_asc' => $query->orderBy('current_price', 'asc'),
                    'price_desc' => $query->orderBy('current_price', 'desc'),
                    'name_asc' => $query->orderBy('name', 'asc'),
                    'name_desc' => $query->orderBy('name', 'desc'),
                    'created_at_asc' => $query->orderBy('created_at', 'asc'),
                    default => $query->orderBy('created_at', 'desc'), // created_at_desc
                };
            }, function ($query) {
                // Mặc định nếu không gửi tham số sort
                $query->orderBy('created_at', 'desc');
            })
            ->paginate($request->integer('per_page', 10));

        return RoomResource::collection($rooms)
            ->additional([
                'stats' => $this->getRoomStats($request),
            ])
            ->response();
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

            'currentResidents' => fn($query) => $query
                ->with('tenant:id,full_name,phone,email,id_card_number')
                ->orderByRaw("role = 'representative' desc")
                ->orderBy('id'),
            'reservations' => fn($query) => $query->where('status', 'pending'),
            'invoices' => fn($query) => $query->whereIn('status', ['issued', 'partially_paid', 'overdue']),
            'latestInvoice'
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
     * Hỗ trợ thêm ảnh mới, xóa ảnh cũ và đổi ảnh bìa.
     */
    public function update(UpdateRoomRequest $request, int $id): RoomResource
    {
        $room = Room::with(['images' => fn($query) => $query->orderBy('sort_order')])
            ->whereHas('property', fn($query) => $query->where('user_id', $request->user()->id))
            ->findOrFail($id);

        $storedPaths = [];
        $pathsToDeleteAfterCommit = [];

        try {
            DB::beginTransaction();

            $validated = $request->validated();

            $images = $request->file('images', []);

            $deletedImageIds = $validated['deleted_image_ids'] ?? [];

            $coverImageId = isset($validated['cover_image_id'])
                ? (int) $validated['cover_image_id']
                : null;

            $coverImageIndex = isset($validated['cover_image_index'])
                ? (int) $validated['cover_image_index']
                : null;

            unset(
                $validated['images'],
                $validated['deleted_image_ids'],
                $validated['cover_image_id'],
                $validated['cover_image_index']
            );

            $oldPrice = $room->current_price;
            $newPrice = $validated['current_price'] ?? null;

            //1. Cập nhật thông tin phòng
            $room->update($validated);

            //2. Ghi lịch sử giá nếu thay đổi
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

            //3. Đồng bộ ảnh phòng
            $imageSyncResult = $this->roomService->syncImages(
                room: $room,
                newImages: $images,
                deletedImageIds: $deletedImageIds,
                coverImageId: $coverImageId,
                coverImageIndex: $coverImageIndex
            );

            $storedPaths = $imageSyncResult['stored_paths'];
            $pathsToDeleteAfterCommit = $imageSyncResult['paths_to_delete_after_commit'];

            DB::commit();

            //4. Sau khi DB commit thành công mới xóa file ảnh cũ 
            $this->roomService->deleteFiles($pathsToDeleteAfterCommit);

            $room->load([
                'property',
                'images' => fn($query) => $query->orderBy('sort_order'),
            ]);

            return new RoomResource($room);
        } catch (Throwable $exception) {
            DB::rollBack();

            //Nếu lỗi sau khi đã upload ảnh mới thì xóa file mới để tránh rác storage
            $this->roomService->deleteFiles($storedPaths);

            throw $exception;
        }
    }

    /**
     * Xóa phòng — chỉ được xóa khi status = available.
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        $room = Room::with('images')
            ->whereHas('property', fn($query) => $query->where('user_id', $request->user()->id))
            ->findOrFail($id);

        if ($room->status !== RoomStatus::Available) {
            throw new BusinessException('Chỉ có thể xóa phòng đang ở trạng thái trống.');
        }

        $pathsToDeleteAfterCommit = [];

        try {
            DB::beginTransaction();

            $pathsToDeleteAfterCommit = $this->roomService
                ->deleteAllImageRecords($room);

            $room->delete();

            DB::commit();

            $this->roomService->deleteFiles($pathsToDeleteAfterCommit);

            return response()->json(['message' => 'Xóa phòng thành công.']);
        } catch (Throwable $exception) {
            DB::rollBack();

            throw $exception;
        }
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

    private function getRoomStats(Request $request): array
    {
        $baseQuery = Room::query()
            ->whereHas(
                'property',
                fn($query) =>
                $query->where('user_id', $request->user()->id)
            )
            ->when($request->filled('property_id'), function ($query) use ($request) {
                $query->where('property_id', $request->integer('property_id'));
            });

        $total = (clone $baseQuery)->count();

        $available = (clone $baseQuery)
            ->where('status', RoomStatus::Available->value)
            ->count();

        $occupied = (clone $baseQuery)
            ->where('status', RoomStatus::Occupied->value)
            ->count();

        $maintenance = (clone $baseQuery)
            ->where('status', RoomStatus::Maintenance->value)
            ->count();

        $expectedMonthlyRevenue = (clone $baseQuery)
            ->where('status', RoomStatus::Occupied->value)
            ->sum('current_price');

        $reserved = (clone $baseQuery)
            ->whereHas('reservations', fn($q) => $q->where('status', 'pending'))
            ->count();

        $percent = fn(int $value): int => $total > 0
            ? (int) round(($value / $total) * 100)
            : 0;

        return [
            'total' => $total,

            'occupied' => $occupied,
            'available' => $available,
            'maintenance' => $maintenance,
            'reserved' => $reserved,

            'occupancy_rate' => $percent($occupied),
            'available_rate' => $percent($available),
            'maintenance_rate' => $percent($maintenance),
            'reserved_rate' => $percent($reserved),

            // Chưa có module công nợ/hóa đơn thì tạm để 0.
            'debt_rooms' => 0,
            'debt_rate' => 0,
            'current_debt_amount' => 0,
        ];
    }
}
