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

class CronController extends Controller
{
    public function __construct(
        private readonly WebPushService $webPushService
    ) {}

    public function remindUtilityReadings(Request $request)
    {
        if ($request->header('X-Cron-Secret') !== env('CRON_SECRET', 'YOUR_SECRET_KEY')) {
            abort(403, 'Unauthorized');
        }

        // Lấy thông tin ngày, tháng chuẩn của kỳ thu tiền sắp tới
        $targetDate = now()->addDays(3);
        $targetDay = $targetDate->day;
        $targetMonth = $targetDate->format('Y-m');

        $disabledLandlordIds = Setting::where('key', 'auto_remind_utility')
            ->where('value', 'false')
            ->pluck('user_id')
            ->toArray();

        $leases = Lease::with(['tenant', 'room.property'])
            ->where('status', 'active')
            ->where('billing_day', $targetDay)
            ->get();

        $userIdsToNotify = [];
        $notificationsToInsert = [];

        foreach ($leases as $lease) {
            if (!$lease->tenant || !$lease->tenant->user_id) continue;

            $landlordId = $lease->room->property->user_id;

            if (in_array($landlordId, $disabledLandlordIds)) {
                continue;
            }

            // Check số điện nước theo $targetMonth
            $hasReading = MeterReading::where('lease_id', $lease->id)
                ->where('reading_date', 'like', $targetMonth . '%')
                ->exists();

            // Check chống spam thông báo theo $targetDate
            $hasNotified = Notification::where('target_id', $lease->room_id)
                ->where('target_type', 'room')
                ->where('type', 'billing')
                ->where('title', 'like', '%Đã đến hạn chốt điện/nước%')
                ->whereMonth('created_at', $targetDate->month)
                ->whereYear('created_at', $targetDate->year)
                ->exists();

            if (!$hasReading && !$hasNotified) {
                $userId = $lease->tenant->user_id;
                $userIdsToNotify[] = $userId;

                $notificationsToInsert[] = [
                    'user_id' => $landlordId, // Thông báo này tạo dưới danh nghĩa chủ trọ
                    'title' => "Đã đến hạn chốt điện/nước phòng {$lease->room->name}",
                    'content' => "Vui lòng nhập chỉ số điện nước của tháng này để hệ thống tạo hóa đơn.",
                    'type' => 'system',
                    'target_type' => 'room',
                    'target_id' => $lease->room_id,
                    'action_url' => '/tenant/utilities?action=submit_reading', // Gắn link deep-link
                    'status' => 'published',
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
        }

        if (empty($userIdsToNotify)) {
            return response()->json(['message' => 'Không có phòng nào cần nhắc nhở.']);
        }

        // Insert vào DB tốc độ cao
        Notification::insert($notificationsToInsert);

        // Gọi đồng bộ Push Notifications
        $subscriptions = PushSubscription::whereIn('user_id', array_unique($userIdsToNotify))->get();
        if ($subscriptions->isNotEmpty()) {
            $payload = [
                'title' => 'Chốt chỉ số điện nước ⚡💧',
                'body' => 'Vui lòng nhập chỉ số điện nước để chốt hóa đơn kỳ này.',
                'url' => '/tenant/utilities?action=submit_reading',
                'icon' => '/icon.png'
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
