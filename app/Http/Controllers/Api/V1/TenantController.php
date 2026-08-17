<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\Domain\BusinessException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\StoreTenantRequest;
use App\Http\Requests\Tenant\UpdateTenantRequest;
use App\Http\Resources\Tenant\TenantResource;
use App\Models\Room;
use App\Models\Tenant;
use App\Services\AuthService;
use App\Services\TenantService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Throwable;

class TenantController extends Controller
{
    public function __construct(
        private readonly TenantService $tenantService,
        private readonly AuthService $authService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $query = Tenant::with([
            // Đã tối ưu chọn cột - Giữ nguyên
            'leases:id,tenant_id,room_id,start_date,end_date,status',
            'leases.room:id,property_id,name',
            'leases.room.property:id,name',
            'leaseMembers:id,lease_id,tenant_id,move_in_date,move_out_date',
            'leaseMembers.lease:id,room_id,status',
            'leaseMembers.lease.room:id,property_id,name',
            'leaseMembers.lease.room.property:id,name',
        ])
            ->where('owner_id', $request->user()->id);

        // 1. Filter theo từ khóa
        if ($request->filled('search')) {
            $keyword = '%' . trim((string) $request->search) . '%';
            $query->where(function ($sub) use ($keyword): void {
                $sub->where('full_name', 'like', $keyword)
                    ->orWhere('phone', 'like', $keyword)
                    ->orWhere('id_card_number', 'like', $keyword);
            });
        }

        // 2. TỐI ƯU Filter theo Khu nhà (Property) bằng Subquery Union
        if ($request->filled('property_id')) {
            $propertyId = $request->integer('property_id');
            $query->whereIn('id', function ($q) use ($propertyId) {
                $q->select('tenant_id')->from('leases')
                    ->join('rooms', 'leases.room_id', '=', 'rooms.id')
                    ->where('rooms.property_id', $propertyId)
                    ->union(
                        DB::table('lease_members')->select('tenant_id')
                            ->join('leases', 'lease_members.lease_id', '=', 'leases.id')
                            ->join('rooms', 'leases.room_id', '=', 'rooms.id')
                            ->where('rooms.property_id', $propertyId)
                    );
            });
        }

        // 3. TỐI ƯU Filter theo Phòng (Room) bằng Subquery Union
        if ($request->filled('room_id')) {
            $roomId = $request->integer('room_id');
            $query->whereIn('id', function ($q) use ($roomId) {
                $q->select('tenant_id')->from('leases')->where('room_id', $roomId)
                    ->union(
                        DB::table('lease_members')->select('tenant_id')
                            ->join('leases', 'lease_members.lease_id', '=', 'leases.id')
                            ->where('leases.room_id', $roomId)
                    );
            });
        }

        // 4. TỐI ƯU Filter Trạng thái (Status) - KHÔNG DÙNG whereHas / whereDoesntHave
        if ($request->filled('status')) {
            if ($request->status === 'active') {
                $query->whereIn('id', function ($q) {
                    $q->select('tenant_id')->from('leases')->where('status', 'active')
                        ->union(DB::table('lease_members')->select('tenant_id')->whereNull('move_out_date'));
                });
            } elseif ($request->status === 'pending') {
                // Chưa từng thuê (không có trong leases, cũng không có trong lease_members)
                $query->whereNotIn('id', function ($q) {
                    $q->select('tenant_id')->from('leases')
                        ->union(DB::table('lease_members')->select('tenant_id'));
                });
            } elseif ($request->status === 'left') {
                // Đã từng thuê nhưng hiện tại không thuê (Có trong lịch sử, nhưng không nằm trong nhóm active)
                $query->whereIn('id', function ($q) {
                    $q->select('tenant_id')->from('leases')
                        ->union(DB::table('lease_members')->select('tenant_id'));
                })->whereNotIn('id', function ($q) {
                    $q->select('tenant_id')->from('leases')->where('status', 'active')
                        ->union(DB::table('lease_members')->select('tenant_id')->whereNull('move_out_date'));
                });
            }
        }

        $tenants = $query->latest()->paginate($request->integer('per_page', 15));

        return TenantResource::collection($tenants)->response();
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $tenant = Tenant::where('owner_id', $request->user()->id)
            ->with([
                'leases:id,tenant_id,room_id,start_date,end_date,status',
                'leases.room:id,property_id,name',
                'leases.room.property:id,name',
                'leaseMembers:id,lease_id,tenant_id,move_in_date,move_out_date',
                'leaseMembers.lease:id,room_id,status',
                'leaseMembers.lease.room:id,property_id,name',
                'leaseMembers.lease.room.property:id,name',
            ])
            ->findOrFail($id);

        return (new TenantResource($tenant))->response();
    }

    public function update(UpdateTenantRequest $request, int $id): JsonResponse
    {
        $tenant = Tenant::where('owner_id', $request->user()->id)->findOrFail($id);

        $data = $request->validated();
        unset($data['id_card_front_image'], $data['id_card_back_image']);

        if ($request->hasFile('id_card_front_image')) {
            if ($tenant->id_card_front_image) {
                Storage::disk('public')->delete($tenant->id_card_front_image);
            }
            $ext = $request->file('id_card_front_image')->extension();
            $data['id_card_front_image'] = $request->file('id_card_front_image')
                ->storeAs("tenants/{$tenant->id}", "id_card_front.{$ext}", 'public');
        }

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

    public function destroy(Request $request, int $id): JsonResponse
    {
        $tenant = Tenant::where('owner_id', $request->user()->id)->findOrFail($id);

        if ($tenant->leaseMembers()->whereNull('move_out_date')->exists()) {
            throw new BusinessException('Không thể xóa khách thuê đang còn ở ghép. Vui lòng thực hiện rời phòng trước.');
        }

        if ($tenant->leases()->exists()) {
            throw new BusinessException('Không thể xóa hồ sơ này vì khách đang đứng tên Hợp đồng.');
        }

        $tenant->leaseMembers()->delete();
        Storage::disk('public')->deleteDirectory("tenants/{$tenant->id}");
        $userId = $tenant->user_id;

        $tenant->delete();

        // Xóa tài khoản hệ thống của khách thuê (nếu tồn tại)
        if ($userId) {
            \App\Models\User::where('id', $userId)->delete();
        }
        return response()->json(['message' => 'Xóa khách thuê thành công.']);
    }

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

                $tenant = $this->tenantService->createProfile($data, $request->user()->id);
                $createdTenantId = $tenant->id;

                $this->tenantService->createMemberResidence(
                    tenant: $tenant,
                    lease: $lease,
                    data: [
                        'relationship' => $data['relationship'] ?? 'roommate',
                        'move_in_date' => $data['move_in_date'] ?? now()->toDateString(),
                        'note' => $data['note'] ?? null,
                    ]
                );

                return $tenant->refresh()->load([
                    'leases:id,tenant_id,room_id,start_date,end_date,status',
                    'leases.room:id,property_id,name',
                    'leases.room.property:id,name',
                    'leaseMembers:id,lease_id,tenant_id,move_in_date,move_out_date',
                    'leaseMembers.lease:id,room_id,status',
                    'leaseMembers.lease.room:id,property_id,name',
                    'leaseMembers.lease.room.property:id,name',
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
            'lease_id'      => ['required', 'integer', 'exists:leases,id'],
            'move_out_date' => ['nullable', 'date'],
        ]);

        $tenant = Tenant::where('owner_id', $request->user()->id)->findOrFail($id);

        $tenant = $this->tenantService->markTenantLeft(
            tenant: $tenant,
            leaseId: (int) $data['lease_id'],
            moveOutDate: $data['move_out_date'] ?? null
        );

        return (new TenantResource($tenant))
            ->additional(['message' => 'Đã ghi nhận khách thuê rời phòng.'])
            ->response();
    }

    public function resetPassword(Request $request, int $id): JsonResponse
    {
        $tenant = Tenant::where('owner_id', $request->user()->id)->findOrFail($id);
        $this->tenantService->resetTenantPassword($tenant);

        return response()->json([
            'message' => 'Đã khôi phục mật khẩu mặc định (Số điện thoại) thành công.'
        ]);
    }
}
