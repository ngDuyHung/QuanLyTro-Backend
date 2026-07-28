<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Exceptions\Domain\BusinessException;
use App\Http\Requests\Incident\ResolveIncidentRequest;
use App\Http\Requests\Incident\StoreIncidentRequest;
use App\Http\Resources\Incident\IncidentResource;
use App\Models\Incident;
use App\Models\Property;
use App\Services\IncidentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class IncidentController extends Controller
{
    public function __construct(
        private readonly IncidentService $incidentService
    ) {}

    /**
     * API: Lấy danh sách sự cố của chủ trọ đang đăng nhập.
     * * Hỗ trợ bộ lọc filter:
     * - property_id: lọc theo khu nhà
     * - room_id: lọc theo phòng
     * - status: lọc theo trạng thái sự cố
     * - priority: lọc theo mức độ nghiêm trọng
     */
    public function index(Request $request): JsonResponse
    {
        $incidents = Incident::query()
            ->with(['property:id,name', 'room:id,name,property_id', 'reportedByTenant:id,full_name,phone', 'images'])
            ->whereHas('property', function ($query) use ($request): void {
                $query->where('user_id', $request->user()->id);
            })
            ->when($request->query('property_id'), function ($query, $propertyId): void {
                $query->where('property_id', $propertyId);
            })
            ->when($request->query('room_id'), function ($query, $roomId): void {
                $query->where('room_id', $roomId);
            })
            ->when($request->query('status'), function ($query, $status): void {
                $query->where('status', $status);
            })
            ->when($request->query('priority'), function ($query, $priority): void {
                $query->where('priority', $priority);
            })
            ->latest()
            ->paginate($request->integer('per_page', 15));

        return IncidentResource::collection($incidents)->response();
    }

    /**
     * API: Tạo mới một sự cố (Báo cáo sự cố)
     */
    public function store(StoreIncidentRequest $request): JsonResponse
    {
        // Chặn quyền sở hữu khu nhà trước khi cho phép tạo record liên quan
        $this->assertPropertyOwned(
            propertyId: (int) $request->property_id,
            userId: $request->user()->id
        );

        $incident = $this->incidentService->createIncident(
            data: $request->validated(),
            images: $request->file('images', []),
            userId: $request->user()->id
        );

        return (new IncidentResource($incident))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * API: Xem chi tiết thông tin một sự cố
     */
    public function show(Request $request, int $id): JsonResponse
    {
        $incident = $this->findOwnedIncident($id, $request->user()->id);

        $incident->load(['property', 'room', 'reportedByTenant', 'images', 'financialTransaction', 'invoice']);

        return (new IncidentResource($incident))->response();
    }

    /**
     * API: Cập nhật thông tin nhẹ của sự cố
     * (Chỉ áp dụng khi sự cố đang ở trạng thái Chờ tiếp nhận - Pending)
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $incident = $this->findOwnedIncident($id, $request->user()->id);

        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'category' => ['required', 'string', 'in:electrical,water,furniture,security,other'],
            'priority' => ['required', 'string', 'in:low,normal,high,emergency'],
        ], [
            'title.required' => 'Tiêu đề sự cố không được để trống.',
            'category.in' => 'Loại sự cố không hợp lệ.',
            'priority.in' => 'Mức độ ưu tiên không hợp lệ.',
        ]);

        $incident = $this->incidentService->updateIncident($incident, $data);

        return (new IncidentResource($incident))->response();
    }

    /**
     * API: Tiếp nhận sự cố (Chuyển trạng thái sang Đang xử lý - Processing)
     */
    public function process(Request $request, int $id): JsonResponse
    {
        $incident = $this->findOwnedIncident($id, $request->user()->id);
        $incident = $this->incidentService->processIncident($incident);

        return (new IncidentResource($incident))->response();
    }

    /**
     * API: Chốt giải quyết xong sự cố (Resolved)
     * Nhận chi phí sửa chữa, bên chịu phí và lưu ảnh nghiệm thu sau sửa
     */
    public function resolve(ResolveIncidentRequest $request, int $id): JsonResponse
    {
        $incident = $this->findOwnedIncident($id, $request->user()->id);

        $incident = $this->incidentService->resolveIncident(
            incident: $incident,
            data: $request->validated(),
            images: $request->file('images', []),
            userId: $request->user()->id
        );

        return (new IncidentResource($incident))->response();
    }

    /**
     * API: Hủy bỏ sự cố (Cancelled)
     */
    public function cancel(Request $request, int $id): JsonResponse
    {
        $incident = $this->findOwnedIncident($id, $request->user()->id);
        $incident = $this->incidentService->cancelIncident($incident);

        return (new IncidentResource($incident))->response();
    }

    /**
     * API: Xóa vĩnh viễn sự cố
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        $incident = $this->findOwnedIncident($id, $request->user()->id);

        $this->incidentService->deleteIncident($incident);

        return response()->json([
            'success' => true,
            'message' => 'Xóa sự cố thành công.',
        ]);
    }

    /**
     * Hàm dùng chung kiểm tra ownership và tìm bản ghi sự cố hợp lệ
     */
    private function findOwnedIncident(int $id, int $userId): Incident
    {
        return Incident::query()
            ->whereHas('property', function ($query) use ($userId): void {
                $query->where('user_id', $userId);
            })
            ->findOrFail($id);
    }

    /**
     * Hàm dùng chung đảm bảo khu nhà thuộc quyền quản lý của chủ trọ hiện tại
     */
    private function assertPropertyOwned(int $propertyId, int $userId): void
    {
        $exists = Property::query()
            ->where('id', $propertyId)
            ->where('user_id', $userId)
            ->exists();

        if (!$exists) {
            throw new BusinessException('Bạn không có quyền thao tác trên khu nhà này.');
        }
    }

    /**
     * API: Đếm số lượng sự cố đang cần xử lý (pending & processing)
     */
    public function countActive(Request $request): JsonResponse
    {
        // Sử dụng hàm count() của Query Builder, nó sẽ sinh ra câu lệnh SQL: SELECT COUNT(*)
        // Không tốn RAM để load model, không query các relationships không cần thiết.
        $count = Incident::query()
            ->whereHas('property', function ($query) use ($request): void {
                $query->where('user_id', $request->user()->id);
            })
            ->whereIn('status', ['pending', 'processing'])
            ->count();

        return response()->json([
            'success' => true,
            'count' => $count
        ]);
    }
}
