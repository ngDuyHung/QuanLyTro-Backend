<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\ServicePrice\StoreServicePriceRequest;
use App\Http\Requests\ServicePrice\UpdateServicePriceRequest;
use App\Http\Resources\ServicePrice\ServicePriceResource;
use App\Models\ServicePrice;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;


class ServicePriceController extends Controller
{
    /**
     * Các phương thức CRUD cho ServicePrice sẽ được định nghĩa ở đây
     * Lấy danh sách giá dịch vụ thuộc khu nhà nào đó (có thể phân trang, lọc theo tên thuộc tính).
     **/
    public function index(Request $request, int $propertyId): JsonResponse
    {
        $servicePrices = ServicePrice::where('property_id', $propertyId)
            ->when(
                $request->search,
                fn ($q) => $q->where('service_type', 'like', "%{$request->search}%")
            )
            ->latest()
            ->paginate($request->integer('per_page', 15));

        return ServicePriceResource::collection($servicePrices)->response();
    }

    /**
     * Xem chi tiết một giá dịch vụ.
     **/
    public function show(int $id): JsonResponse
    {
        $servicePrice = ServicePrice::findOrFail($id);
        return (new ServicePriceResource($servicePrice))->response();
    }
    /** 
     * Thêm mới một giá dịch vụ.
     **/
    public function store(StoreServicePriceRequest $request): JsonResponse
    {
        $servicePrice = ServicePrice::create($request->validated());
        return (new ServicePriceResource($servicePrice))->response()->setStatusCode(201);
    }


    /**
     * Cập nhật thông tin giá dịch vụ.
     **/
    public function update(UpdateServicePriceRequest $request, int $id): JsonResponse
    {
        $servicePrice = ServicePrice::findOrFail($id);
        $servicePrice->update($request->validated());
        return (new ServicePriceResource($servicePrice))->response();
    }

    /**
     * Xóa một giá dịch vụ.
     **/
    public function destroy(int $id): JsonResponse
    {
        $servicePrice = ServicePrice::findOrFail($id);
        $servicePrice->delete();
        return response()->json(['message' => 'Giá dịch vụ đã được xóa thành công.']);
    }
}
