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
use App\Models\Lease;
use App\Models\Room;
use App\Services\AuthService;
use Illuminate\Support\Facades\DB;
use Throwable;

class TenantController extends Controller
{

    public function __construct(
        private readonly TenantService $tenantService,
        private readonly AuthService $authService
    ) {}
    /**
     * Lấy danh sách khách thuê (search tên/SĐT/CCCD, phân trang).
     * Ownership check: qua hợp đồng -> phòng -> khu nhà.
     */
    public function index(Request $request): JsonResponse
    {
        $tenants = Tenant::with([
            'currentResidence.room.property',
            'currentResidence.lease',
        ])
            ->whereHas(
                'roomResidents.room.property',
                fn($query) => $query->where('user_id', $request->user()->id)
            )
            ->when($request->filled('search'), function ($query) use ($request): void {
                $keyword = '%' . trim((string) $request->search) . '%';

                $query->where(function ($sub) use ($keyword): void {
                    $sub->where('full_name', 'like', $keyword)
                        ->orWhere('phone', 'like', $keyword)
                        ->orWhere('id_card_number', 'like', $keyword);
                });
            })
            ->when($request->filled('room_id'), function ($query) use ($request): void {
                $query->whereHas(
                    'roomResidents',
                    fn($sub) => $sub->where('room_id', $request->integer('room_id'))
                );
            })
            ->when($request->filled('status'), function ($query) use ($request): void {
                $query->whereHas(
                    'roomResidents',
                    fn($sub) => $sub->where('status', $request->status)
                );
            })
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
            'roomResidents.room.property',
            'roomResidents.lease',
            'currentResidence.room.property',
            'currentResidence.lease',
            'leases.room.property',
            'leaseMembers.lease.room.property',
        ])
            ->whereHas(
                'roomResidents.room.property',
                fn($query) => $query->where('user_id', $request->user()->id)
            )
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
        $tenant = Tenant::whereHas(
            'roomResidents.room.property',
            fn($query) => $query->where('user_id', $request->user()->id)
        )->findOrFail($id);

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
     * Xóa khách thuê.
     * Xóa cả thư mục ảnh CCCD trong storage.
     * Ownership check: qua hợp đồng -> phòng -> khu nhà.
     * Chặn xóa nếu khách thuê đang còn cư trú hoặc đang đứng tên hợp đồng
     * Nếu hợp đồng đã bị xóa, thì vẫn có thể xóa khách thuê (dọn rác mồ côi).
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        $tenant = Tenant::whereHas(
            'roomResidents.room.property',
            fn($query) => $query->where('user_id', $request->user()->id)
        )->findOrFail($id);

        // 1. Chặn nếu đang ở trong phòng (chưa rời đi)
        if (
            $tenant->roomResidents()
            ->whereIn('status', ['pending', 'active'])
            ->exists()
        ) {
            throw new BusinessException('Không thể xóa khách thuê đang còn cư trú. Vui lòng thực hiện rời phòng trước.');
        }

        // 2. Chặn nếu khách ĐANG ĐỨNG TÊN một hợp đồng thực sự (active hoặc ended)
        // Trừ khi hợp đồng đó đã bị chủ trọ xóa hẳn khỏi Database (trường hợp nhập nhầm)
        if ($tenant->leases()->exists()) {
            throw new BusinessException('Không thể xóa hồ sơ này vì khách thuê đang đứng tên hợp đồng trong hệ thống. Vui lòng xóa hợp đồng trước nếu đây là dữ liệu nhầm lẫn.');
        }

        // 3. Tiến hành "Dọn rác" trước khi xóa (Ngăn chặn lỗi SQL 1451 RESTRICT)
        // Nếu code chạy đến đây, nghĩa là Hợp đồng đã bị xóa -> Các record này chỉ là rác mồ côi
        $tenant->leaseMembers()->delete();
        $tenant->roomResidents()->delete();

        // 4. Xóa ảnh và xóa khách thuê
        Storage::disk('public')->deleteDirectory("tenants/{$tenant->id}");
        // 5. Lưu trữ user_id để xử lý xóa tài khoản hệ thống sau khi xóa tenant
        $userId = $tenant->user_id;

        // Xóa hồ sơ khách thuê trước để tránh lỗi ràng buộc khóa ngoại (Foreign Key Constraint)
        $tenant->delete();

        // 6. Xóa tài khoản hệ thống của khách thuê (nếu tồn tại)
        if ($userId) {
            \App\Models\User::where('id', $userId)->delete();
        }
        return response()->json(['message' => 'Xóa khách thuê thành công.']);
    }


    /**
     * Thêm khách thuê vào một phòng đang có hợp đồng active.
     */
    public function store(StoreTenantRequest $request): JsonResponse
    {
        $data = $request->validated();

        $createdTenantId = null;

        try {
            $tenant = DB::transaction(function () use ($data, $request, &$createdTenantId): Tenant {
                $room = Room::with(['property', 'activeLease'])
                    ->whereHas('property', fn($query) => $query->where('user_id', $request->user()->id))
                    ->findOrFail($data['room_id']);

                $lease = $room->activeLease;

                if (!$lease) {
                    throw new BusinessException('Phòng này chưa có hợp đồng đang hiệu lực. Vui lòng tạo hợp đồng trước khi thêm khách thuê.');
                }
                // --- CẤP TÀI KHOẢN TỰ ĐỘNG ---
                $accountTenant = $this->authService->getOrCreateTenantUser([
                    'name'      => $data['full_name'],
                    'phone'     => $data['phone'],
                    'email'     => $data['email'] ?? null,
                    'password'  => $data['phone'], // Mặc định pass là SĐT
                ]);
                $data['user_id'] = $accountTenant->id; // Gắn user_id vào data để tạo Profile
                // ------------------------------------

                $tenant = $this->tenantService->createProfile($data);
                $createdTenantId = $tenant->id;

                $this->tenantService->createMemberResidence(
                    tenant: $tenant,
                    lease: $lease,
                    data: [
                        'relationship' => $data['relationship'] ?? 'other',
                        'move_in_date' => $data['move_in_date'] ?? now()->toDateString(),
                        'note' => $data['note'] ?? null,
                    ]
                );

                return $tenant->refresh()->load([
                    'currentResidence.room.property',
                    'currentResidence.lease',
                    'roomResidents.room.property',
                ]);
            });

            return (new TenantResource($tenant))
                ->additional(['message' => 'Thêm khách thuê vào phòng thành công.'])
                ->response()
                ->setStatusCode(201);
        } catch (Throwable $exception) {
            if ($createdTenantId) {
                $this->tenantService->deleteTenantFiles($createdTenantId);
            }

            throw $exception;
        }
    }

    public function leave(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'move_out_date' => ['nullable', 'date'],
        ]);

        $tenant = Tenant::whereHas(
            'roomResidents.room.property',
            fn($query) => $query->where('user_id', $request->user()->id)
        )->findOrFail($id);

        $tenant = $this->tenantService->markTenantLeft(
            tenant: $tenant,
            ownerId: $request->user()->id,
            moveOutDate: $data['move_out_date'] ?? null
        );

        return (new TenantResource($tenant))
            ->additional(['message' => 'Đã ghi nhận khách thuê rời phòng.'])
            ->response();
    }

    /**
     * Khôi phục mật khẩu khách thuê
     */
    public function resetPassword(Request $request, int $id): JsonResponse
    {
        // 1. Kiểm tra ownership: Chủ trọ chỉ được thao tác với khách của mình
        $tenant = Tenant::whereHas(
            'roomResidents.room.property',
            fn($query) => $query->where('user_id', $request->user()->id)
        )->findOrFail($id);

        // 2. Gọi Service thực thi
        $this->tenantService->resetTenantPassword($tenant);

        return response()->json([
            'message' => 'Đã khôi phục mật khẩu mặc định (Số điện thoại) thành công.'
        ]);
    }
}
