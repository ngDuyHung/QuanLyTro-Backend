<?php

namespace App\Listeners;

use App\Services\SePayTransactionService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use SePay\SePay\Events\SePayWebhookEvent;

class SePayWebhookListener
{
    /**
     * Create the event listener.
     */
    public function __construct(
        private readonly SePayTransactionService $sePayTransactionService
    ) {}

    /**
     * Xử lý webhook do package sepayvn/laravel-sepay phát ra.
     *
     * Package sẽ:
     * - nhận request từ /api/sepay/webhook,
     * - kiểm tra token theo SEPAY_WEBHOOK_TOKEN,
     * - parse dữ liệu,
     * - phát SePayWebhookEvent.
     *
     * Listener này chỉ tập trung vào nghiệp vụ của hệ thống:
     * - lưu sepay_transactions,
     * - match hóa đơn,
     * - tạo financial_transactions,
     * - tạo financial_transaction_allocations.
     */
    public function handle(SePayWebhookEvent $event): void
    {
        $info = property_exists($event, 'info')
            ? $event->info
            : null;

        $this->sePayTransactionService->handleWebhook(
            payload: $event->sePayWebhookData,
            info: $info
        );
    }
}
