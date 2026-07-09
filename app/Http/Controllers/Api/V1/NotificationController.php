<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Notification\StoreNotificationRequest;
use App\Http\Requests\Notification\UpdateNotificationRequest;
use App\Http\Resources\Notification\NotificationResource;
use App\Models\Notification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        // THÊM EAGER LOADING VỚI with()
        $notifications = Notification::with(['targetProperty', 'targetRoom.property'])
            ->where('user_id', $request->user()->id)
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

        return (new NotificationResource($notification))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        // THÊM EAGER LOADING CHO HÀM SHOW
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
}
