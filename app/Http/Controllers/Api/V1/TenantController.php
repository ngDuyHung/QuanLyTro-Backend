<?php

//declare(strict_types=1); dùng để bật chế độ kiểm tra kiểu dữ liệu nghiêm ngặt trong PHP. 
//Khi được bật, PHP sẽ ném lỗi nếu có sự không khớp về kiểu dữ liệu giữa các biến, tham số hàm, hoặc giá trị trả về so với những gì được khai báo. Điều này giúp phát hiện lỗi sớm hơn trong quá trình phát triển và đảm bảo rằng mã nguồn hoạt động đúng như mong đợi.
declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\Domain\BusinessException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\UpdateTenantRequest;
use App\Http\Resources\Tenant\TenantResource;
use App\Models\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use App\Services\TenantService;
use App\Http\Requests\Tenant\StoreTenantRequest;
class TenantController extends Controller
{

    public function __construct(
        private readonly TenantService $tenantService
    ) {}
    /**
     * Lấy danh sách khách thuê (search tên/SĐT/CCCD, phân trang).
     * Ownership check: qua hợp đồng -> phòng -> khu nhà.
     */
    public function index(Request $request): JsonResponse
    {
        $tenants = Tenant::whereHas('leases.room.property', fn($q) => $q->where('user_id', $request->user()->id))
            ->when(
                $request->search,
                fn($q) => $q->where(function ($sub) use ($request): void {
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
            'leases.room:id,name,property_id',
            'leases.room.property:id,name',
        ])
            ->whereHas('leases.room.property', fn($q) => $q->where('user_id', $request->user()->id))
            ->findOrFail($id);

        return (new TenantResource($tenant))->response();
    }

    /**
     * Cập nhật thông tin khách thuê.
     * Nếu có ảnh mới → xóa ảnh cũ rồi lưu ảnh mới vào tenants/{id}/
     * Ownership check: qua hợp đồng -> phòng -> khu nhà.
     */
    public function update(UpdateTenantRequest $request, int $id): JsonResponse
    {
        $tenant = Tenant::whereHas('leases.room.property', fn($q) => $q->where('user_id', $request->user()->id))
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
        $tenant = Tenant::whereHas('leases.room.property', fn($q) => $q->where('user_id', $request->user()->id))
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


    public function store(StoreTenantRequest $request): JsonResponse
    {
        $tenant = $this->tenantService->createTenant($request->validated());

        return response()->json([
            'message' => 'Thêm khách thuê thành công.',
            'data' => [
                'id' => $tenant->id,

                'name' => $tenant->full_name,
                'full_name' => $tenant->full_name,

                'phone' => $tenant->phone,
                'email' => $tenant->email,

                'cccd' => $tenant->id_card_number,
                'id_card_number' => $tenant->id_card_number,

                'id_card_front_image' => $tenant->id_card_front_image
                    ? asset('storage/' . ltrim($tenant->id_card_front_image, '/'))
                    : null,

                'id_card_back_image' => $tenant->id_card_back_image
                    ? asset('storage/' . ltrim($tenant->id_card_back_image, '/'))
                    : null,

                // Tạm thời để TenantTable hiện được.
                // Sau này phần này sẽ lấy từ leases / lease_members.
                'room' => 'Chưa gắn phòng',
                'area' => null,
                'contractCode' => null,
                'contractDuration' => null,
                'role' => 'representative',
                'status' => 'active',

                'created_at' => $tenant->created_at?->toISOString(),
                'updated_at' => $tenant->updated_at?->toISOString(),
            ],
        ], 201);
    }
}
