<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Lease;
use App\Models\MeterReading;
use App\Models\Notification;
use App\Models\PushSubscription;
use App\Models\Setting;
use App\Services\WebPushService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CronController extends Controller
{
    public function __construct(
        private readonly WebPushService $webPushService
    ) {}

    public function remindUtilityReadings(Request $request)
    {
        // 1. Bảo mật
        if ($request->header('X-Cron-Secret') !== env('CRON_SECRET', 'YOUR_SECRET_KEY')) {
            abort(403, 'Unauthorized');
        }

        $targetDay = now()->addDays(3)->day;
        $currentMonth = now()->format('Y-m'); // Định dạng yyyy-mm

        // LẤY DANH SÁCH CHỦ TRỌ ĐÃ "TẮT" TÍNH NĂNG NÀY
        $disabledLandlordIds = Setting::where('key', 'auto_remind_utility')
            ->where('value', 'false')
            ->pluck('user_id')
            ->toArray();

        // 2. Lấy hợp đồng cần nhắc (Thêm 'room.property' để lấy được user_id của chủ trọ )
        $leases = Lease::with(['tenant', 'room.property'])
            ->where('status', 'active')
            ->where('billing_day', $targetDay)
            ->get();

        $userIdsToNotify = [];
        $notificationsToInsert = [];

        foreach ($leases as $lease) {
            if (!$lease->tenant || !$lease->tenant->user_id) continue;
            
            $landlordId = $lease->room->property->user_id;

            // NẾU CHỦ TRỌ TẮT TÍNH NĂNG -> BỎ QUA KHÔNG NHẮC NHỞ
            if (in_array($landlordId, $disabledLandlordIds)) {
                continue;
            }

            // 1. Kiểm tra xem ĐÃ CHỐT CHỈ SỐ tháng này chưa
            $hasReading = MeterReading::where('lease_id', $lease->id)
                ->where('reading_date', 'like', $currentMonth . '%')
                ->exists();

            // 2. KIỂM TRA ĐÃ GỬI THÔNG BÁO CHƯA (Chống trùng lặp tuyệt đối)
            // Tìm xem trong tháng hiện tại, đã có thông báo loại 'billing' nào gửi đến phòng này chưa
            $hasNotified = Notification::where('target_id', $lease->room_id)
                ->where('target_type', 'room')
                ->where('type', 'billing')
                ->where('title', 'like', '%Đã đến hạn chốt điện/nước%') // Đảm bảo đúng loại thông báo
                ->whereMonth('created_at', now()->month)
                ->whereYear('created_at', now()->year)
                ->exists();

            // CHỈ GỬI KHI: Chưa chốt chỉ số VÀ Chưa từng gửi thông báo nhắc nhở trong tháng này
            if (!$hasReading && !$hasNotified) {
                $userId = $lease->tenant->user_id;
                $userIdsToNotify[] = $userId;

                $notificationsToInsert[] = [
                    'user_id' => $userId,
                    'title' => "Đã đến hạn chốt điện/nước phòng {$lease->room->name}",
                    'content' => "Vui lòng nhập chỉ số điện nước của tháng này để hệ thống tạo hóa đơn.",
                    'type' => 'billing',
                    'target_type' => 'room',
                    'target_id' => $lease->room_id,
                    'status' => 'published',
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
        }

        if (empty($userIdsToNotify)) {
            return response()->json(['message' => 'Không có phòng nào cần nhắc nhở.']);
        }

        // 3. Insert thông báo vào DB hệ thống
        Notification::insert($notificationsToInsert);

        // 4. Lấy tất cả Device Token của các user cần nhắc
        $subscriptions = PushSubscription::whereIn('user_id', $userIdsToNotify)->get();

        // 5. Bắn Push Notification xuống thiết bị qua WebPushService
        if ($subscriptions->isNotEmpty()) {
            $payload = [
                'title' => 'Chốt chỉ số điện nước ⚡💧',
                'body' => 'Vui lòng nhập chỉ số điện nước để chốt hóa đơn kỳ này.',
                'url' => '/utilities', // Đường dẫn Frontend PWA sẽ mở khi click vào thông báo
                'icon' => '/icon.png' // Icon ứng dụng của bạn
            ];

            $this->webPushService->sendNotifications($subscriptions, $payload);
        }

        return response()->json([
            'message' => 'Cronjob hoàn tất',
            'reminded_users' => count(array_unique($userIdsToNotify)),
            'push_sent' => $subscriptions->count()
        ]);
    }
}
