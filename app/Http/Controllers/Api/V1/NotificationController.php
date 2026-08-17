<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Notification\StoreNotificationRequest;
use App\Http\Requests\Notification\UpdateNotificationRequest;
use App\Http\Resources\Notification\NotificationResource;
use App\Models\Notification;
use App\Models\Lease;
use App\Models\PushSubscription;
use App\Services\WebPushService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class NotificationController extends Controller
{
    public function __construct(
        private readonly WebPushService $webPushService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $query = Notification::with(['targetProperty', 'targetRoom.property'])
            ->where('user_id', $request->user()->id);

        // THÊM LOGIC LỌC TAB TẠI ĐÂY
        if ($request->input('is_system') === 'true') {
            $query->where('type', 'system');
        } else {
            $query->where('type', '!=', 'system');
        }

        $notifications = $query
            ->when($request->filled('search'), function ($q) use ($request) {
                $q->where('title', 'like', '%' . $request->search . '%');
            })
            ->when($request->filled('status'), function ($q) use ($request) {
                $q->where('status', $request->status);
            })
            ->when($request->filled('target_type'), function ($q) use ($request) {
                $q->where('target_type', $request->target_type);
            })
            ->orderByDesc('is_pinned')
            ->latest()
            ->paginate($request->integer('per_page', 15));

        return NotificationResource::collection($notifications)->response();
    }

    public function store(StoreNotificationRequest $request): JsonResponse
    {
        $data = $request->validated();
        $data['user_id'] = $request->user()->id;
        $data['is_pinned'] = filter_var($request->is_pinned, FILTER_VALIDATE_BOOLEAN);

        $notification = Notification::create($data);

        // GỌI PUSH ĐỒNG BỘ: Nếu trạng thái là published, gửi push ngay lập tức
        if ($notification->status === 'published') {
            $this->sendPushNotification($notification);
        }

        return (new NotificationResource($notification))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $notification = Notification::with(['targetProperty', 'targetRoom.property'])
            ->where('user_id', $request->user()->id)
            ->findOrFail($id);

        return (new NotificationResource($notification))->response();
    }

    public function update(UpdateNotificationRequest $request, int $id): JsonResponse
    {
        $notification = Notification::where('user_id', $request->user()->id)->findOrFail($id);

        $data = $request->validated();
        if ($request->has('is_pinned')) {
            $data['is_pinned'] = filter_var($request->is_pinned, FILTER_VALIDATE_BOOLEAN);
        }

        $notification->update($data);

        return (new NotificationResource($notification))->response();
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $notification = Notification::where('user_id', $request->user()->id)->findOrFail($id);
        $notification->delete();

        return response()->json(['message' => 'Xóa thông báo thành công.']);
    }

    // API MỚI: Dùng cho nút "Gửi lại Push" trên giao diện chủ nhà
    public function resendPush(Request $request, int $id): JsonResponse
    {
        $notification = Notification::where('user_id', $request->user()->id)->findOrFail($id);

        if ($notification->status !== 'published') {
            return response()->json(['message' => 'Chỉ có thể gửi Push cho thông báo đã đăng.'], 400);
        }

        $this->sendPushNotification($notification);

        return response()->json(['message' => 'Đã gửi thông báo đẩy đến các khách thuê thành công.']);
    }

    /**
     * Hàm helper: Trích xuất danh sách khách thuê và gọi WebPushService
     */
    private function sendPushNotification(Notification $notification): void
    {
        $landlordId = $notification->user_id;

        $baseQuery = Lease::query()->where('status', 'active');

        // TỐI ƯU 1: Loại bỏ whereHas lồng nhau, dùng pluck để lấy mảng ID
        if ($notification->target_type === 'all') {
            $propertyIds = \App\Models\Property::where('user_id', $landlordId)->pluck('id');
            $roomIds = \App\Models\Room::whereIn('property_id', $propertyIds)->pluck('id');
            $baseQuery->whereIn('room_id', $roomIds);
        } elseif ($notification->target_type === 'property') {
            $roomIds = \App\Models\Room::where('property_id', $notification->target_id)->pluck('id');
            $baseQuery->whereIn('room_id', $roomIds);
        } elseif ($notification->target_type === 'room') {
            $baseQuery->where('room_id', $notification->target_id);
        }

        // TỐI ƯU 2: Pluck ID trực tiếp từ DB, KHÔNG dùng ->get() để tránh tràn RAM
        $tenantIds = $baseQuery->pluck('tenant_id')->toArray();

        // Query riêng bảng Tenant để lấy user_id
        $userIds = \App\Models\Tenant::whereIn('id', $tenantIds)
            ->whereNotNull('user_id')
            ->pluck('user_id')
            ->toArray();

        $userIds = array_unique($userIds);

        if (!empty($userIds)) {
            $subscriptions = PushSubscription::whereIn('user_id', $userIds)->get();

            if ($subscriptions->isNotEmpty()) {
                $payload = [
                    'title' => $notification->title,
                    // Lọc bỏ HTML tag trong Jodit Editor để lấy text thuần làm body
                    'body' => Str::limit(strip_tags($notification->content), 100),
                    'url' => $notification->action_url ?? '/tenant/notifications',
                    'icon' => '/icon.png'
                ];

                $this->webPushService->sendNotifications($subscriptions, $payload);
            }
        }
    }
}
