<?php

//declare(strict_types=1); dùng để bật chế độ kiểm tra kiểu dữ liệu nghiêm ngặt trong PHP. 
//Khi được bật, PHP sẽ ném lỗi nếu có sự không khớp về kiểu dữ liệu giữa các biến, tham số hàm, hoặc giá trị trả về so với những gì được khai báo. Điều này giúp phát hiện lỗi sớm hơn trong quá trình phát triển và đảm bảo rằng mã nguồn hoạt động đúng như mong đợi.
declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\Domain\BusinessException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\StoreTenantRequest;
use App\Http\Requests\Tenant\UpdateTenantRequest;
use App\Http\Resources\Tenant\TenantResource;
use App\Models\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class TenantController extends Controller
{
    /**
     * Lấy danh sách khách thuê (search tên/SĐT/CCCD, phân trang).
     * Ownership check: qua hợp đồng -> phòng -> khu nhà.
     */
    public function index(Request $request): JsonResponse
    {
        $tenants = Tenant::whereHas('leases.room.property', fn ($q) => $q->where('user_id', $request->user()->id))
            ->when(
                $request->search,
                fn ($q) => $q->where(function ($sub) use ($request): void {
                    $keyword = "%{$request->search}%";
                    $sub->where('full_name', 'like', $keyword)
                        ->orWhere('phone', 'like', $keyword)
                        ->orWhere('id_card_number', 'like', $keyword);
                })
            )
            ->latest()
            ->paginate($request->integer('per_page', 15));

        return TenantResource::collection($tenants)->response();
    }

    /**
     * Xem chi tiết một khách thuê (kèm danh sách hợp đồng).
     * Ownership check: qua hợp đồng -> phòng -> khu nhà.
     */
    public function show(Request $request, int $id): JsonResponse
    {
        $tenant = Tenant::with([
                'leases.room:id,full_name,property_id',
                'leases.room.property:id,full_name',
            ])
            ->whereHas('leases.room.property', fn ($q) => $q->where('user_id', $request->user()->id))
            ->findOrFail($id);

        return (new TenantResource($tenant))->response();
    }

    /**
     * Tạo hồ sơ khách thuê mới.
     * Bất kỳ chủ trọ đã đăng nhập đều có thể tạo.
     * Ảnh CCCD lưu vào storage/app/public/tenants/{id}/
     */
    public function store(StoreTenantRequest $request): JsonResponse
    {
        $data = $request->validated();

        // Lọc ra file ảnh để xử lý riêng sau khi có ID
        unset($data['id_card_front_image'], $data['id_card_back_image']);

        // Tạo tenant trước để lấy ID làm tên thư mục
        $tenant = Tenant::create($data);

        // Upload ảnh mặt trước CCCD vào thư mục tenants/{id}/
        if ($request->hasFile('id_card_front_image')) {
            $ext = $request->file('id_card_front_image')->extension();
            $tenant->id_card_front_image = $request->file('id_card_front_image')
                ->storeAs("tenants/{$tenant->id}", "id_card_front.{$ext}", 'public');
        }

        // Upload ảnh mặt sau CCCD vào thư mục tenants/{id}/
        if ($request->hasFile('id_card_back_image')) {
            $ext = $request->file('id_card_back_image')->extension();
            $tenant->id_card_back_image = $request->file('id_card_back_image')
                ->storeAs("tenants/{$tenant->id}", "id_card_back.{$ext}", 'public');
        }

        $tenant->save();

        return (new TenantResource($tenant))->response()->setStatusCode(201);
    }

    /**
     * Cập nhật thông tin khách thuê.
     * Nếu có ảnh mới → xóa ảnh cũ rồi lưu ảnh mới vào tenants/{id}/
     * Ownership check: qua hợp đồng -> phòng -> khu nhà.
     */
    public function update(UpdateTenantRequest $request, int $id): JsonResponse
    {
        $tenant = Tenant::whereHas('leases.room.property', fn ($q) => $q->where('user_id', $request->user()->id))
            ->findOrFail($id);

        $data = $request->validated();
        unset($data['id_card_front_image'], $data['id_card_back_image']);

        // Cập nhật ảnh mặt trước nếu có file mới
        if ($request->hasFile('id_card_front_image')) {
            if ($tenant->id_card_front_image) {
                Storage::disk('public')->delete($tenant->id_card_front_image);
            }
            $ext = $request->file('id_card_front_image')->extension();
            $data['id_card_front_image'] = $request->file('id_card_front_image')
                ->storeAs("tenants/{$tenant->id}", "id_card_front.{$ext}", 'public');
        }

        // Cập nhật ảnh mặt sau nếu có file mới
        if ($request->hasFile('id_card_back_image')) {
            if ($tenant->id_card_back_image) {
                Storage::disk('public')->delete($tenant->id_card_back_image);
            }
            $ext = $request->file('id_card_back_image')->extension();
            $data['id_card_back_image'] = $request->file('id_card_back_image')
                ->storeAs("tenants/{$tenant->id}", "id_card_back.{$ext}", 'public');
        }

        $tenant->update($data);

        return (new TenantResource($tenant))->response();
    }

    /**
     * Xóa khách thuê (chỉ khi không có hợp đồng đang active).
     * Xóa cả thư mục ảnh CCCD trong storage.
     * Ownership check: qua hợp đồng -> phòng -> khu nhà.
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        $tenant = Tenant::whereHas('leases.room.property', fn ($q) => $q->where('user_id', $request->user()->id))
            ->findOrFail($id);

        // Kiểm tra không còn hợp đồng đang active
        if ($tenant->leases()->where('status', 'active')->exists()) {
            throw new BusinessException('Không thể xóa khách thuê đang có hợp đồng thuê phòng.');
        }

        // Xóa toàn bộ thư mục ảnh CCCD của khách thuê
        Storage::disk('public')->deleteDirectory("tenants/{$tenant->id}");

        $tenant->delete();

        return response()->json(['message' => 'Xóa khách thuê thành công.']);
    }
}
