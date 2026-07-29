<?php

namespace App\Services;

use Minishlink\WebPush\WebPush;
use Minishlink\WebPush\Subscription;
use Illuminate\Support\Facades\Log;

class WebPushService
{
    private WebPush $webPush;

    public function __construct()
    {
        $auth = [
            'VAPID' => [
                'subject' => env('VAPID_SUBJECT'),
                'publicKey' => env('VAPID_PUBLIC_KEY'),
                'privateKey' => env('VAPID_PRIVATE_KEY'),
            ],
        ];

        // Khởi tạo thư viện
        $this->webPush = new WebPush($auth);
        
        // Tùy chọn: Set timeout nếu share hosting của bạn chậm
        $this->webPush->setReuseVAPIDHeaders(true);
    }

    /**
     * Hàm gửi thông báo cho danh sách các subscriptions
     */
    public function sendNotifications($subscriptions, array $payloadData): void
    {
        $payload = json_encode($payloadData); // Dữ liệu sẽ đẩy xuống React Service Worker

        foreach ($subscriptions as $sub) {
            $subscription = Subscription::create([
                'endpoint' => $sub->endpoint,
                'publicKey' => $sub->public_key,
                'authToken' => $sub->auth_token,
            ]);

            // Xếp hàng thông báo vào queue của thư viện
            $this->webPush->sendOneNotification($subscription, $payload, ['batchSize' => 1000]);
        }

        // Bắn toàn bộ thông báo đi cùng 1 lúc (tốt cho Share Hosting để tiết kiệm thời gian)
        $responses = $this->webPush->flush();

        // Xử lý phản hồi (xóa các token đã chết/hết hạn)
        foreach ($responses as $response) {
            if (!$response->isSuccess()) {
                Log::warning('Push failed: ' . $response->getReason());
                // Nếu endpoint bị hết hạn (người dùng chặn thông báo), xóa khỏi DB
                if ($response->isSubscriptionExpired()) {
                    \App\Models\PushSubscription::where('endpoint', $response->getRequest()->getUri()->__toString())->delete();
                }
            }
        }
    }
}