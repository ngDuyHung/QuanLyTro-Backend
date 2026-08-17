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
            'activeLease.tenant:id,full_name,phone,email,id_card_number',
            'activeLease.members' => fn($q) => $q->whereNull('move_out_date')->with('tenant:id,full_name,phone,email,id_card_number'),
            'reservations' => fn($query) => $query->where('status', 'pending'),
        ])
            // Tính tổng tiền nợ của hợp đồng active
            ->withSum([
                'invoices as unpaid_amount' => fn($query) => $query
                    ->whereIn('status', ['issued', 'partially_paid', 'overdue'])
                    ->whereHas('lease', fn($q) => $q->where('status', 'active'))
            ], 'remaining_amount')

            // Kiểm tra xem phòng có tồn tại hóa đơn nào của hợp đồng active không (để xác định trạng thái paid/unbilled)
            ->withExists([
                'invoices as has_any_invoice' => fn($query) => $query
                    ->where('status', '!=', 'draft') // Bỏ qua hóa đơn nháp
                    ->whereHas('lease', fn($q) => $q->where('status', 'active'))
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
            ->when($request->filled('sort'), function ($query) use ($request) {
                match ($request->sort) {
                    'sort_order_asc' => $query->orderBy('sort_order', 'asc'),
                    'sort_order_desc' => $query->orderBy('sort_order', 'desc'),
                    'price_asc' => $query->orderBy('current_price', 'asc'),
                    'price_desc' => $query->orderBy('current_price', 'desc'),
                    'name_asc' => $query->orderBy('name', 'asc'),
                    'name_desc' => $query->orderBy('name', 'desc'),
                    'created_at_asc' => $query->orderBy('created_at', 'asc'),
                    default => $query->orderBy('created_at', 'desc'),
                };
            }, function ($query) {
                // FALLBACK AN TOÀN: Mặc định giữ nguyên logic cũ nếu Frontend không gửi tham số sort
                $query->orderBy('sort_order', 'asc')->orderBy('id', 'desc');
            })
            // ---> KẾT THÚC ĐOẠN CODE THÊM MỚI <---
            ->paginate($request->integer('per_page', 15));

        return RoomResource::collection($rooms)->response();
    }


    public function all(Request $request): JsonResponse
    {
        $rooms = Room::with([
            'property',
            'images' => fn($query) => $query->orderBy('sort_order'),
            'activeLease.tenant:id,full_name,phone,email,id_card_number',
            'activeLease.members' => fn($q) => $q->whereNull('move_out_date')->with('tenant:id,full_name,phone,email,id_card_number'),
            'reservations' => fn($query) => $query->where('status', 'pending'),
        ])
            ->withSum([
                'invoices as unpaid_amount' => fn($query) => $query
                    ->whereIn('status', ['issued', 'partially_paid', 'overdue'])
                    ->whereHas('lease', fn($q) => $q->where('status', 'active'))
            ], 'remaining_amount')

            ->withExists([
                'invoices as has_any_invoice' => fn($query) => $query
                    ->where('status', '!=', 'draft')
                    ->whereHas('lease', fn($q) => $q->where('status', 'active'))
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
                    'sort_order_asc' => $query->orderBy('sort_order', 'asc'),
                    'sort_order_desc' => $query->orderBy('sort_order', 'desc'),
                    'price_asc' => $query->orderBy('current_price', 'asc'),
                    'price_desc' => $query->orderBy('current_price', 'desc'),
                    'name_asc' => $query->orderBy('name', 'asc'),
                    'name_desc' => $query->orderBy('name', 'desc'),
                    'created_at_asc' => $query->orderBy('created_at', 'asc'),
                    default => $query->orderBy('created_at', 'desc'), // created_at_desc
                };
            }, function ($query) {
                // Mặc định nếu Frontend KHÔNG gửi tham số sort
                $query->orderBy('sort_order', 'asc')->orderBy('id', 'desc');
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

            'activeLease.tenant:id,full_name,phone,email,id_card_number',
            'activeLease.members' => fn($q) => $q->whereNull('move_out_date')->with('tenant:id,full_name,phone,email,id_card_number'),
            'reservations' => fn($query) => $query->where('status', 'pending'),
            'invoices' => fn($query) => $query->whereIn('status', ['draft', 'issued', 'partially_paid', 'overdue'])
                ->whereHas('lease', fn($q) => $q->where('status', 'active')),
            'latestInvoice' => fn($query) => $query
                ->whereHas('lease', fn($q) => $q->where('status', 'active'))
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
        // Kiểm tra xem phòng đã từng có hợp đồng nào chưa
        if ($room->leases()->exists()) {
            throw new BusinessException('Không thể xóa phòng đã hoặc đang có lịch sử hợp đồng thuê. Bạn chỉ có thể cập nhật trạng thái hoặc ẩn phòng này.');
        }

        // Thêm kiểm tra phòng có reservation chưa (Cọc giữ chỗ)
        if ($room->reservations()->exists()) {
            throw new BusinessException('Không thể xóa phòng đang có lịch sử cọc giữ chỗ.');
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

        // 1. Tính số phòng đang nợ tiền (Có hóa đơn trạng thái phát hành/nợ và số tiền nợ > 0)
        $debtRooms = (clone $baseQuery)
            ->whereHas('invoices', function ($q) {
                $q->whereIn('status', ['issued', 'partially_paid', 'overdue'])
                    ->where('remaining_amount', '>', 0)
                    ->whereHas('lease', fn($leaseQuery) => $leaseQuery->where('status', 'active'));
            })
            ->count();

        // 2. Tính tổng số tiền đang nợ (chuẩn bị sẵn nếu Frontend muốn hiển thị tổng tiền nợ)
        $currentDebtAmount = \App\Models\Invoice::query()
            ->whereIn('room_id', (clone $baseQuery)->select('id'))
            ->whereIn('status', ['issued', 'partially_paid', 'overdue'])
            ->where('remaining_amount', '>', 0)
            ->whereHas('lease', fn($q) => $q->where('status', 'active'))
            ->sum('remaining_amount');

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

            // Dữ liệu công nợ đã được tính toán realtime
            'debt_rooms' => $debtRooms,
            'debt_rate' => $percent($debtRooms),
            'current_debt_amount' => (int) $currentDebtAmount,
        ];
    }


    /**
     * Lấy danh sách khách hàng có Lịch sử thanh toán xấu (Hay nợ, Nợ dai)
     */
    public function debtors(Request $request): JsonResponse
    {
        $today = now()->startOfDay();

        $rooms = Room::with([
            'property:id,name',
            // SỬA LẠI DÒNG LEASES ĐỂ EAGER LOAD THÊM TENANT:
            'leases' => fn($query) => $query->where('status', 'active')->with(['tenant:id,full_name,phone', 'invoices.allocations']),
        ])
            ->whereHas('property', fn($query) => $query->where('user_id', $request->user()->id))
            ->when($request->filled('property_id'), fn($query) => $query->where('property_id', $request->integer('property_id')))
            ->whereHas('leases', fn($query) => $query->where('status', 'active'))
            ->get();

        $debtors = collect();

        foreach ($rooms as $room) {
            $lease = $room->leases->first();
            if (!$lease) continue;

            $tenant = $lease->tenant;

            $invoices = $lease->invoices;
            $totalInvoices = $invoices->count();
            if ($totalInvoices === 0) continue;

            $latePaymentCount = 0;
            $currentOverdueAmount = 0;
            $totalUnpaidAmount = 0;
            $textDetails = []; // Mảng lưu chi tiết từng vi phạm

            foreach ($invoices as $invoice) {
                if ($invoice->remaining_amount > 0) {
                    $totalUnpaidAmount += $invoice->remaining_amount;
                }

                if (!$invoice->due_date) continue;
                $dueDate = \Carbon\Carbon::parse($invoice->due_date)->startOfDay();

                $isLate = false;

                // 1. ĐANG NỢ QUÁ HẠN HIỆN TẠI
                if ($invoice->remaining_amount > 0 && $today->gt($dueDate)) {
                    $isLate = true;
                    $currentOverdueAmount += $invoice->remaining_amount;

                    //difInDays dùng để tính số ngày quá hạn
                    $daysOverdue = $today->diffInDays($dueDate);
                    $formattedAmount = number_format((float)$invoice->remaining_amount, 0, ',', '.');

                    $textDetails[] = "HĐ {$invoice->invoice_code}: Đang nợ {$formattedAmount}đ (Quá hạn {$daysOverdue} ngày)";
                }
                // 2. LỊCH SỬ TỪNG TRẢ TRỄ
                elseif ($invoice->paid_amount > 0) {
                    $lastPayment = $invoice->allocations->max('allocated_at');
                    if ($lastPayment) {

                        $lastPaymentDate = \Carbon\Carbon::parse($lastPayment)->startOfDay();
                        if ($lastPaymentDate->gt($dueDate)) {
                            $isLate = true;
                            $daysLate = $lastPaymentDate->diffInDays($dueDate);
                            $textDetails[] = "HĐ {$invoice->invoice_code}: Đã đóng trễ ({$daysLate} ngày)";
                        }
                    }
                }

                if ($isLate) {
                    $latePaymentCount++;
                }
            }

            // ĐIỀU KIỆN ĐƯA VÀO DANH SÁCH ĐEN: Đang có nợ quá hạn HOẶC từng trễ từ 2 lần trở lên
            if ($currentOverdueAmount > 0 || $latePaymentCount >= 2) {
                $debtors->push([
                    'room_id' => $room->id,
                    'room_name' => $room->name,
                    'property_name' => $room->property->name ?? 'Không xác định',
                    'tenant_name' => $tenant ? $tenant->full_name : 'Khách thuê (Không xác định)',
                    'tenant_phone' => $tenant ? $tenant->phone : 'N/A',
                    'total_unpaid_amount' => $totalUnpaidAmount,
                    'current_overdue_amount' => $currentOverdueAmount,
                    'late_payment_count' => $latePaymentCount,
                    'violation_details' => $textDetails, // Đẩy mảng chi tiết ra API
                ]);
            }
        }

        $debtors = $debtors->sortByDesc('current_overdue_amount')->sortByDesc('late_payment_count')->values();

        return response()->json([
            'success' => true,
            'data' => $debtors,
            'summary' => [
                'total_overdue_amount' => $debtors->sum('current_overdue_amount'),
                'total_bad_tenants' => $debtors->count(),
            ]
        ]);
    }
}
