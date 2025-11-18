<?php

namespace App\Services\SocialMedia;

use Illuminate\Support\Facades\Log;

class WebhookService
{
    public function process(array $payload): void
    {
        // Minimal processing: log entries for now.
        // TODO: Dispatch jobs based on event type (messages, comments, post_created, etc.)
        if (! empty($payload['entry'])) {
            foreach ($payload['entry'] as $entry) {
                Log::info('Meta Webhook Entry', ['entry' => $entry]);
            }
        } else {
            Log::info('Meta Webhook', ['payload' => $payload]);
        }
    }
}
