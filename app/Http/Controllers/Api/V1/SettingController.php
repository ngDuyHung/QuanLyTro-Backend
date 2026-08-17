<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Setting\ExportLeasePdfRequest;
use App\Http\Requests\Setting\ExportLedgerPdfRequest;
use App\Http\Requests\Setting\SaveContractTemplateRequest;
use App\Http\Requests\Setting\SaveLedgerTemplateRequest;
use App\Http\Resources\Setting\SettingResource;
use App\Models\Lease;
use App\Models\MeterReading;
use App\Models\Notification;
use App\Models\PushSubscription;
use App\Models\Setting;
use App\Services\SettingService;
use App\Services\WebPushService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SettingController extends Controller
{
    public function __construct(
        private readonly SettingService $settingService
    ) {}

    /**
     * API: Lấy mẫu hợp đồng
     */
    public function getContractTemplate(Request $request): JsonResponse
    {
        $template = $this->settingService->getContractTemplate($request->user()->id);

        // Trả về dữ liệu qua API Resource đúng chuẩn kiến trúc hệ thống
        return (new SettingResource($template))
            ->additional(['success' => true])
            ->response();
    }

    /**
     * API: Lưu mẫu hợp đồng
     */
    public function saveContractTemplate(SaveContractTemplateRequest $request): JsonResponse
    {
        $setting = $this->settingService->saveContractTemplate(
            $request->user()->id,
            $request->validated('template')
        );

        // Trả về dữ liệu mẫu mới lưu qua API Resource
        return (new SettingResource($setting))
            ->additional([
                'success' => true,
                'message' => 'Lưu mẫu hợp đồng thành công.'
            ])
            ->response();
    }

    /**
     * API: Xuất file PDF
     */
    public function exportLeasePdf(ExportLeasePdfRequest $request, int $id)
    {
        // Chắc chắn dữ liệu đã hợp lệ 100% mới chạy xuống đây
        $pdf = $this->settingService->generateLeasePdf($id, $request->user()->id);

        return $pdf->download("Hop_dong_thue_phong_{$id}.pdf");
    }



    /**
     * Lấy mẫu Hóa đơn
     */
    public function getInvoiceTemplate(Request $request)
    {
        $userId = $request->user()->id;
        $template = $this->settingService->getInvoiceTemplate($userId);

        return response()->json([
            'data' => [
                'template' => $template
            ]
        ]);
    }

    /**
     * Lưu mẫu Hóa đơn
     */
    public function saveInvoiceTemplate(Request $request)
    {
        $request->validate([
            'template' => 'nullable|string'
        ]);

        $userId = $request->user()->id;
        $this->settingService->saveInvoiceTemplate($userId, $request->input('template', ''));

        return response()->json([
            'message' => 'Đã lưu cấu hình mẫu hóa đơn thành công.'
        ]);
    }

    /**
     * Xuất PDF Hóa đơn
     */
    public function exportInvoicePdf(Request $request, $id)
    {
        $userId = $request->user()->id;

        // Gọi hàm từ Service bạn vừa thêm
        $pdf = $this->settingService->generateInvoicePdf((int) $id, $userId);

        // Trả về file PDF để trình duyệt có thể tải xuống
        return $pdf->download("Hoa_don_{$id}.pdf");
    }

    // /**
    //  * Lấy mẫu Sổ kế toán
    //  */
    // public function getLedgerTemplate(Request $request): JsonResponse
    // {
    //     $template = $this->settingService->getLedgerTemplate($request->user()->id);

    //     return (new SettingResource($template))
    //         ->additional(['success' => true])
    //         ->response();
    // }

    // /**
    //  * Lưu mẫu Sổ kế toán
    //  */
    // public function saveLedgerTemplate(SaveLedgerTemplateRequest $request): JsonResponse
    // {
    //     $setting = $this->settingService->saveLedgerTemplate(
    //         $request->user()->id,
    //         $request->validated('template')
    //     );

    //     return (new SettingResource($setting))
    //         ->additional([
    //             'success' => true,
    //             'message' => 'Lưu mẫu sổ kế toán thành công.'
    //         ])
    //         ->response();
    // }

    /**
     * Xuất PDF Sổ kế toán
     */
    public function exportLedgerPdf(ExportLedgerPdfRequest $request, int $id)
    {
        $pdf = $this->settingService->generateLedgerPdf($id, $request->user()->id);
        return $pdf->download("So_Ke_Toan_S1a_HKD_{$id}.pdf");
    }


    /**
     * Lấy trạng thái cài đặt nhắc nhở
     */
    public function getAutoRemindSetting(Request $request)
    {
        $setting = Setting::where('user_id', $request->user()->id)
            ->where('key', 'auto_remind_utility')
            ->first();

        // Mặc định là true nếu chưa lưu
        $isActive = $setting ? ($setting->value === 'true') : true;

        return response()->json(['auto_remind_utility' => $isActive]);
    }

    /**
     * Bật/Tắt tính năng tự động nhắc nhở
     */
    public function toggleAutoRemind(Request $request)
    {
        $request->validate(['is_active' => 'required|boolean']);

        Setting::updateOrCreate(
            ['user_id' => $request->user()->id, 'key' => 'auto_remind_utility'],
            ['value' => $request->is_active ? 'true' : 'false']
        );

        return response()->json(['message' => 'Cập nhật trạng thái thành công.']);
    }

    /**
     * Test gửi Push Notification đến thiết bị của CHÍNH CHỦ TRỌ
     */
    public function testPushNotification(Request $request, WebPushService $webPushService)
    {
        $userId = $request->user()->id;

        // Tìm thiết bị của chủ trọ đang đăng nhập
        $subscriptions = PushSubscription::where('user_id', $userId)
            ->where('is_active', true)
            ->get();

        if ($subscriptions->isEmpty()) {
            return response()->json([
                'message' => 'Bạn chưa cấp quyền thông báo trên thiết bị này. Vui lòng cấp quyền ở góc URL trình duyệt trước khi test.'
            ], 400);
        }

        $payload = [
            'title' => '🔔 Thông báo thử nghiệm',
            'body' => 'Hệ thống gửi thông báo đẩy đang hoạt động tuyệt vời!',
            'url' => '/settings', // Trỏ về trang cài đặt
            'icon' => '/icon.png'
        ];

        // Gửi qua Service
        $webPushService->sendNotifications($subscriptions, $payload);

        return response()->json(['message' => 'Đã gửi thông báo test thành công. Vui lòng kiểm tra màn hình thiết bị.']);
    }

    /**
     * API: Ép chạy luồng nhắc nhở chốt điện nước (Dành cho Demo/Test)
     */
    public function forceRemindUtilityReadings(Request $request, WebPushService $webPushService)
    {
        $landlordId = $request->user()->id;
        $currentMonth = now()->format('Y-m'); // Dùng tháng hiện tại để test

        // 1. TỐI ƯU: Lấy danh sách phòng thuộc quyền sở hữu (Bỏ whereHas)
        $propertyIds = \App\Models\Property::where('user_id', $landlordId)->pluck('id');
        $roomIdsAuth = \App\Models\Room::whereIn('property_id', $propertyIds)->pluck('id');

        $leases = Lease::with(['tenant', 'room'])
            ->whereIn('room_id', $roomIdsAuth)
            ->where('status', 'active')
            ->get();

        // 2. TỐI ƯU N+1 QUERY: Kéo toàn bộ dữ liệu chỉ số và thông báo 1 lần duy nhất
        $leaseIds = $leases->pluck('id')->toArray();
        $roomIds = $leases->pluck('room_id')->toArray();

        $readingsThisMonth = MeterReading::whereIn('lease_id', $leaseIds)
            ->where('reading_date', 'like', $currentMonth . '%')
            ->pluck('lease_id')
            ->toArray();

        $notificationsThisMonth = Notification::whereIn('target_id', $roomIds)
            ->where('target_type', 'room')
            ->where('type', 'system')
            ->where('title', 'like', '%[TEST LỤẬN VĂN]%')
            ->whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year)
            ->pluck('target_id')
            ->toArray();

        $userIdsToNotify = [];
        $notificationsToInsert = [];

        foreach ($leases as $lease) {
            if (!$lease->tenant || !$lease->tenant->user_id) continue;

            // 3. TỐI ƯU: Kiểm tra ngay trên RAM (array) thay vì query Database
            $hasReading = in_array($lease->id, $readingsThisMonth);
            $hasNotified = in_array($lease->room_id, $notificationsThisMonth);

            if (!$hasReading && !$hasNotified) {
                $userIdsToNotify[] = $lease->tenant->user_id;

                $notificationsToInsert[] = [
                    'user_id' => $landlordId,
                    'title' => "[TEST] Đã đến hạn chốt điện/nước phòng {$lease->room->name}",
                    'content' => "Đây là thông báo demo từ hệ thống. Vui lòng nhập chỉ số điện nước.",
                    'type' => 'system',
                    'target_type' => 'room',
                    'target_id' => $lease->room_id,
                    'action_url' => '/tenant/utilities?action=submit_reading&lease_id=' . $lease->id,
                    'status' => 'published',
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
        }

        if (empty($userIdsToNotify)) {
            return response()->json([
                'message' => 'Toàn bộ các phòng của bạn đã chốt chỉ số, hoặc đã được test thông báo trong tháng này. Không có ai để gửi thêm.'
            ], 400);
        }

        // Insert vào bảng Notifications
        Notification::insert($notificationsToInsert);

        // Lấy Token và Bắn Push
        $subscriptions = PushSubscription::whereIn('user_id', array_unique($userIdsToNotify))->get();
        if ($subscriptions->isNotEmpty()) {
            $payload = [
                'title' => 'Chốt chỉ số điện nước (Demo) ⚡💧',
                'body' => 'Hệ thống đang chạy test workflow. Nhấp vào đây để xem.',
                'action_url' => '/tenant/utilities?action=submit_reading&lease_id=' . $lease->id,
                'icon' => '/icon.png'
            ];

            $webPushService->sendNotifications($subscriptions, $payload);
        }

        return response()->json([
            'message' => 'Đã chạy luồng Test thành công!',
            'reminded_rooms' => count($notificationsToInsert),
            'push_sent' => $subscriptions->count()
        ]);
    }
}
