<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\Domain\BusinessException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Property\StorePropertyRequest;
use App\Http\Requests\Property\UpdatePropertyRequest;
use App\Http\Resources\Property\PropertyResource;
use App\Models\Property;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;


use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Throwable;

class PropertyController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $properties = $this->propertyQueryWithRoomStats($request)
            // 1. Tìm kiếm theo text
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = trim((string) $request->search);

                $query->where(function ($subQuery) use ($search) {
                    $subQuery
                        ->where('name', 'like', "%{$search}%")
                        ->orWhere('code', 'like', "%{$search}%")
                        ->orWhere('address', 'like', "%{$search}%");
                });
            })
            // 2. Lọc theo trạng thái (active / inactive)
            ->when($request->filled('status'), function ($query) use ($request) {
                $status = $request->input('status');
                // Chỉ lọc nếu status hợp lệ để tránh lỗi
                if (in_array($status, ['active', 'inactive'])) {
                    $query->where('status', $status);
                }
            })
            // 3. Xử lý sắp xếp (sort)
            ->when($request->filled('sort'), function ($query) use ($request) {
                $sort = $request->input('sort');

                // Dùng match (PHP 8.0+) để xử lý code gọn gàng
                match ($sort) {
                    'oldest'    => $query->oldest(),
                    'name_asc'  => $query->orderBy('name', 'asc'),
                    'name_desc' => $query->orderBy('name', 'desc'),
                    default     => $query->latest(), // 'newest' hoặc giá trị không hợp lệ sẽ lấy mới nhất
                };
            }, function ($query) {
                // Mặc định nếu Frontend không gửi tham số sort, luôn sắp xếp mới nhất
                $query->latest();
            })
            // 4. Phân trang
            ->paginate($request->integer('per_page', 15));

        return PropertyResource::collection($properties)->response();
    }

    public function show(Request $request, int $id): PropertyResource
    {
        $property = $this->propertyQueryWithRoomStats($request)
            ->findOrFail($id);

        return new PropertyResource($property);
    }

    public function store(StorePropertyRequest $request): JsonResponse
    {
        $storedPath = null;

        try {
            DB::beginTransaction();

            $data = $request->validated();

            unset($data['cover_image']);

            $data['user_id'] = $request->user()->id;

            $property = Property::create($data);

            // Xử lý tạo giá dịch vụ riêng cho khu nhà
            if (!empty($data['services'])) { // Sửa $validated thành $data
                $servicePricesData = collect($data['services'])->map(function ($service) use ($property) {
                    return [
                        'property_id'    => $property->id,
                        'service_type'   => $service['service_type'],
                        'unit_price'     => $service['unit_price'],
                        'free_units'     => $service['free_units'] ?? 0,
                        'free_unit_type' => $service['free_unit_type'] ?? null,
                        'effective_date' => now()->toDateString(),
                    ];
                })->toArray();

                // Insert nhiều record cùng lúc để tối ưu hiệu suất
                $property->servicePrices()->createMany($servicePricesData);
            }


            if ($request->hasFile('cover_image')) {
                $storedPath = $request
                    ->file('cover_image')
                    ->store("properties/{$property->id}", 'public');

                $property->update([
                    'cover_image_path' => $storedPath,
                ]);
            }

            DB::commit();

            return (new PropertyResource($property->fresh()))
                ->response()
                ->setStatusCode(201);
        } catch (Throwable $exception) {
            DB::rollBack();

            if ($storedPath) {
                Storage::disk('public')->delete($storedPath);
            }

            throw $exception;
        }
    }

    public function update(UpdatePropertyRequest $request, int $id): PropertyResource
    {
        $property = Property::where('user_id', $request->user()->id)
            ->findOrFail($id);

        $newStoredPath = null;
        $oldImagePath = $property->cover_image_path;

        try {
            DB::beginTransaction();

            $data = $request->validated();

            unset($data['cover_image']);

            if ($request->hasFile('cover_image')) {
                $newStoredPath = $request
                    ->file('cover_image')
                    ->store("properties/{$property->id}", 'public');

                $data['cover_image_path'] = $newStoredPath;
            }

            // Cập nhật thông tin cơ bản
            $property->update($data);

            // --- BẮT ĐẦU ĐOẠN XỬ LÝ DỊCH VỤ ---
            $servicesInput = $request->input('services');

            // Bắt trường hợp người dùng xóa hết toàn bộ dịch vụ (gửi cờ empty_services)
            if ($request->input('empty_services') === 'true') {
                $property->servicePrices()->delete();
            }
            // Nếu có danh sách dịch vụ truyền lên
            elseif (is_array($servicesInput) && count($servicesInput) > 0) {
                $submittedTypes = collect($servicesInput)->pluck('service_type')->toArray();

                // 1. Xóa các dịch vụ mà user đã bấm nút "Thùng rác" (bỏ tick)
                $property->servicePrices()->whereNotIn('service_type', $submittedTypes)->delete();

                // 2. Thêm mới hoặc Cập nhật giá các dịch vụ còn lại
                foreach ($servicesInput as $svc) {
                    $property->servicePrices()->updateOrCreate(
                        ['service_type' => $svc['service_type']], // Tìm theo loại dịch vụ
                        [
                            'unit_price'     => $svc['unit_price'],
                            'free_units'     => $svc['free_units'] ?? 0,
                            'free_unit_type' => $svc['free_unit_type'] ?? 'none',
                            'effective_date' => now()->toDateString(), // Khi update không nên ghi đè effective_date liên tục để bảo toàn tính lịch s
                            // Khi update không nên ghi đè effective_date liên tục để bảo toàn tính lịch sử
                        ]
                    );
                }
            }

            DB::commit();

            if ($newStoredPath && $oldImagePath) {
                Storage::disk('public')->delete($oldImagePath);
            }

            return new PropertyResource($property->fresh());
        } catch (Throwable $exception) {
            DB::rollBack();

            if ($newStoredPath) {
                Storage::disk('public')->delete($newStoredPath);
            }

            throw $exception;
        }
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $property = Property::where('user_id', $request->user()->id)
            ->withCount('rooms')
            ->findOrFail($id);

        if ($property->rooms_count > 0) {
            throw new BusinessException('Chỉ có thể xóa khu nhà khi chưa có phòng.');
        }

        $propertyId = $property->id;
        $coverImagePath = $property->cover_image_path;

        $property->delete();

        if ($coverImagePath) {
            Storage::disk('public')->deleteDirectory("properties/{$propertyId}");
        }

        return response()->json([
            'message' => 'Xóa khu nhà thành công.',
        ]);
    }

    //hàm truy vấn khu nhà kèm thống kê số phòng theo trạng thái
    private function propertyQueryWithRoomStats(Request $request)
    {
        return Property::where('user_id', $request->user()->id)
            ->withCount([
                'rooms',

                'rooms as available_rooms_count' => fn($query) =>
                $query->where('status', 'available'),

                'rooms as occupied_rooms_count' => fn($query) =>
                $query->where('status', 'occupied'),

                'rooms as maintenance_rooms_count' => fn($query) =>
                $query->where('status', 'maintenance'),
            ]);
    }
}
