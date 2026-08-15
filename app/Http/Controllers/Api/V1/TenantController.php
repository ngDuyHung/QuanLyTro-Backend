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
        $tenants = Tenant::with([
            'leases.room.property',
            'leaseMembers.lease.room.property',
        ])
            ->where('owner_id', $request->user()->id)
            ->when($request->filled('search'), function ($query) use ($request): void {
                $keyword = '%' . trim((string) $request->search) . '%';
                $query->where(function ($sub) use ($keyword): void {
                    $sub->where('full_name', 'like', $keyword)
                        ->orWhere('phone', 'like', $keyword)
                        ->orWhere('id_card_number', 'like', $keyword);
                });
            })
            ->when($request->filled('property_id'), function ($query) use ($request): void {
                $propertyId = $request->integer('property_id');
                $query->where(function ($q) use ($propertyId) {
                    $q->whereHas('leases.room', fn($sub) => $sub->where('property_id', $propertyId))
                        ->orWhereHas('leaseMembers.lease.room', fn($sub) => $sub->where('property_id', $propertyId));
                });
            })
            ->when($request->filled('room_id'), function ($query) use ($request): void {
                $roomId = $request->integer('room_id');
                $query->where(function ($q) use ($roomId) {
                    $q->whereHas('leases', fn($sub) => $sub->where('room_id', $roomId))
                        ->orWhereHas('leaseMembers.lease', fn($sub) => $sub->where('room_id', $roomId));
                });
            })
            ->when($request->filled('status'), function ($query) use ($request): void {
                if ($request->status === 'active') {
                    $query->where(function ($q) {
                        $q->whereHas('leases', fn($sub) => $sub->where('status', 'active'))
                            ->orWhereHas('leaseMembers', fn($sub) => $sub->whereNull('move_out_date'));
                    });
                } elseif ($request->status === 'left') {
                    $query->where(function ($q) {
                        $q->where(function ($sub) {
                            $sub->whereHas('leases')->orWhereHas('leaseMembers');
                        })->whereDoesntHave('leases', fn($sub) => $sub->where('status', 'active'))
                            ->whereDoesntHave('leaseMembers', fn($sub) => $sub->whereNull('move_out_date'));
                    });
                } elseif ($request->status === 'pending') {
                    $query->whereDoesntHave('leases')->whereDoesntHave('leaseMembers');
                }
            })
            ->latest()
            ->paginate($request->integer('per_page', 15));

        return TenantResource::collection($tenants)->response();
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $tenant = Tenant::where('owner_id', $request->user()->id)
            ->with(['leases.room.property', 'leaseMembers.lease.room.property'])
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
                        'relationship' => $data['relationship'] ?? 'other',
                        'move_in_date' => $data['move_in_date'] ?? now()->toDateString(),
                        'note' => $data['note'] ?? null,
                    ]
                );

                return $tenant->refresh()->load([
                    'leases.room.property',
                    'leaseMembers.lease.room.property',
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
