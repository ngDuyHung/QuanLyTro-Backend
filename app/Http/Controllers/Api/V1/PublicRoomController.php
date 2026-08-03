<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Enums\RoomStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\Room\PublicRoomResource;
use App\Models\Room;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PublicRoomController extends Controller
{
    /**
     * Lấy danh sách phòng trống công khai với đầy đủ bộ lọc
     */
    public function index(Request $request): JsonResponse
    {
        // 1. Chỉ query các phòng Trống và được đánh dấu Public. 
        // Load kèm ảnh, thông tin khu nhà, và chủ trọ.
        $query = Room::with([
            'images' => fn($q) => $q->orderBy('sort_order'),
            'property.user', // Eager load user (chủ trọ) từ property để tránh N+1
            'property.servicePrices'
        ])
            ->where('status', RoomStatus::Available->value)
            ->where('is_public', true);

        // 2. Xử lý Lọc theo khu vực (Khu vực / Quận / Tên đường / Tên phòng)
        if ($request->filled('location')) {
            $location = trim((string) $request->location);
            $query->where(function ($q) use ($location) {
                $q->where('name', 'like', "%{$location}%")
                    ->orWhereHas('property', function ($propQuery) use ($location) {
                        $propQuery->where('address', 'like', "%{$location}%")
                            ->orWhere('name', 'like', "%{$location}%");
                    });
            });
        }

        // 3. Xử lý Lọc theo khoảng giá
        if ($request->filled('min_price')) {
            $query->where('current_price', '>=', $request->integer('min_price'));
        }
        if ($request->filled('max_price')) {
            $query->where('current_price', '<=', $request->integer('max_price'));
        }

        // 4. Xử lý Lọc theo diện tích
        if ($request->filled('min_area')) {
            $query->where('area', '>=', $request->numeric('min_area'));
        }
        if ($request->filled('max_area')) {
            $query->where('area', '<=', $request->numeric('max_area'));
        }

        // 5. Xử lý Lọc theo tiện ích (Nhận vào 1 mảng JSON ví dụ: amenities[]=wifi&amenities[]=air_conditioner)
        if ($request->filled('amenities') && is_array($request->amenities)) {
            foreach ($request->amenities as $amenity) {
                // Laravel tự động map query này thành hàm JSON_CONTAINS của MySQL 8
                $query->whereJsonContains('amenities', $amenity);
            }
        }

        // 6. Xử lý Sắp xếp (Sorting)
        $sort = $request->input('sort', 'newest');
        match ($sort) {
            'price_asc'  => $query->orderBy('current_price', 'asc'),
            'price_desc' => $query->orderBy('current_price', 'desc'),
            'area_desc'  => $query->orderBy('area', 'desc'),
            'area_asc'   => $query->orderBy('area', 'asc'),
            default      => $query->orderBy('created_at', 'desc'), // 'newest'
        };

        // 7. Phân trang (Mặc định 12 phòng 1 trang)
        $rooms = $query->paginate($request->integer('per_page', 12));

        return PublicRoomResource::collection($rooms)->response();
    }

    /**
     * Lấy chi tiết 1 phòng công khai
     */
    public function show(int $id): PublicRoomResource
    {
        $room = Room::with([
            'images' => fn($q) => $q->orderBy('sort_order'),
            'property.user',
            'property.servicePrices'
        ])
            ->where('status', RoomStatus::Available->value)
            ->where('is_public', true)
            ->findOrFail($id);

        return new PublicRoomResource($room);
    }
}
