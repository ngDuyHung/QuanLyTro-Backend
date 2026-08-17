<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\ServicePrice\StoreServicePriceRequest;
use App\Http\Requests\ServicePrice\UpdateServicePriceRequest;
use App\Http\Resources\ServicePrice\ServicePriceResource;
use App\Models\Property;
use App\Models\ServicePrice;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ServicePriceController extends Controller
{
    /**
     * Lấy danh sách giá dịch vụ mặc định toàn hệ thống (property_id = null).
     */
    public function indexGlobal(Request $request): JsonResponse
    {
        $servicePrices = ServicePrice::query() // TỐI ƯU: Bỏ with('priceHistories')
            ->whereNull('property_id')
            ->latest()
            ->paginate($request->integer('per_page', 15));

        return ServicePriceResource::collection($servicePrices)->response();
    }
    /**
     * Lấy giá dịch vụ áp dụng cho khu nhà: ưu tiên giá riêng, fallback sang mặc định.
     */
    public function index(Request $request, int $propertyId): JsonResponse
    {
        // Kiểm tra khu nhà thuộc về chủ trọ hiện tại
        Property::where('user_id', $request->user()->id)->findOrFail($propertyId);

        // Lấy giá riêng của khu nhà (keyed by service_type)
        $propertyPrices = ServicePrice::query() // TỐI ƯU: Bỏ with('priceHistories')
            ->where('property_id', $propertyId)
            ->get()
            ->keyBy(fn($p) => $p->service_type->value);

        // Lấy giá mặc định cho các loại DV chưa có giá riêng
        $assignedTypes = $propertyPrices->keys()->all();

        $globalPrices = ServicePrice::query() // TỐI ƯU: Bỏ with('priceHistories')
            ->whereNull('property_id')
            ->when(!empty($assignedTypes), fn($q) => $q->whereNotIn('service_type', $assignedTypes))
            ->get()
            ->keyBy(fn($p) => $p->service_type->value);

        // Kết hợp: ưu tiên giá riêng khu nhà, fallback sang mặc định nếu chưa có
        $merged = $propertyPrices->merge($globalPrices)->values();

        return ServicePriceResource::collection($merged)->response();
    }

    /**
     * Xem chi tiết một giá dịch vụ.
     */
    public function show(Request $request, int $id): JsonResponse
    {
        $servicePrice = ServicePrice::with('priceHistories')->findOrFail($id);

        // Kiểm tra quyền sở hữu nếu giá dịch vụ thuộc về khu nhà cụ thể
        if ($servicePrice->property_id !== null) {
            Property::where('user_id', $request->user()->id)
                ->findOrFail($servicePrice->property_id);
        }

        return (new ServicePriceResource($servicePrice))->response();
    }

    /**
     * Thêm mới một giá dịch vụ.
     */
    public function store(StoreServicePriceRequest $request): JsonResponse
    {
        $data = $request->validated();

        // Kiểm tra khu nhà thuộc về chủ trọ hiện tại nếu có property_id
        if (!empty($data['property_id'])) {
            Property::where('user_id', $request->user()->id)
                ->findOrFail($data['property_id']);
        }

        $servicePrice = ServicePrice::create($data);

        return (new ServicePriceResource($servicePrice))->response()->setStatusCode(201);
    }

    /**
     * Cập nhật thông tin giá dịch vụ, tự động ghi lịch sử khi unit_price thay đổi.
     */
    public function update(UpdateServicePriceRequest $request, int $id): JsonResponse
    {
        // Log::info('--- DU LIEU REACT GUI LEN API UPDATE GIA ---');
        // Log::info($request->all());
        $servicePrice = ServicePrice::findOrFail($id);

        // Kiểm tra quyền sở hữu nếu giá dịch vụ thuộc về khu nhà cụ thể
        if ($servicePrice->property_id !== null) {
            Property::where('user_id', $request->user()->id)
                ->findOrFail($servicePrice->property_id);
        }

        // Khi cập nhật unit_price: ghi lại lịch sử thay đổi giá
        $oldUnitPrice = $servicePrice->unit_price;
        if ($request->has('unit_price') && $request->unit_price !== (int) $oldUnitPrice) {
            $servicePrice->priceHistories()->create([
                'user_id'      => $request->user()->id,
                'old_price'    => $oldUnitPrice,
                'new_price'    => $request->unit_price,
                'changed_date' => now()->toDateString(), // Cast về chuỗi chuẩn Y-m-d
                'reason'       => 'Cập nhật đơn giá dịch vụ',
            ]);
        }

        $servicePrice->update($request->validated());

        return (new ServicePriceResource($servicePrice))->response();
    }

    /**
     * Xóa một giá dịch vụ.
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        $servicePrice = ServicePrice::findOrFail($id);

        // Kiểm tra quyền sở hữu nếu giá dịch vụ thuộc về khu nhà cụ thể
        if ($servicePrice->property_id !== null) {
            Property::where('user_id', $request->user()->id)
                ->findOrFail($servicePrice->property_id);
        }

        $servicePrice->delete();

        return response()->json(['message' => 'Giá dịch vụ đã được xóa thành công.']);
    }
}
