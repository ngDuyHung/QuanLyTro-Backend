<?php

namespace App\Listeners;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class SePayWebhookListener
{
    /**
     * Create the event listener.
     */
    public function __construct(
       // private readonly SePayTransactionService $sePayTransactionService
    ){}

    /**
     * Handle the event.
     */
    public function handle(object $event): void
    {
        // $this->sePayTransactionService->handleWebhook(
        //     $event->sePayWebhookData,
        //     $event->info ?? null
        // );
    }
}
