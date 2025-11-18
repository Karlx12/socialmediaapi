<?php

namespace App\Http\Controllers\Api\V1\Marketing;

use App\Http\Controllers\Controller;
use App\Services\SocialMedia\WebhookService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class WebhookController extends Controller
{
    public function __construct(protected WebhookService $service) {}

    // Verification: GET /webhook?hub.mode=subscribe&hub.verify_token=TOKEN&hub.challenge=CHALLENGE
    public function verify(Request $request): JsonResponse
    {
        // Query params sometimes arrive as nested 'hub[mode]=subscribe' or as 'hub.mode=subscribe'.
        $hub = $request->query('hub');
        if (is_array($hub)) {
            $mode = $hub['mode'] ?? null;
            $token = $hub['verify_token'] ?? null;
            $challenge = $hub['challenge'] ?? null;
        } else {
            $mode = $request->query('hub.mode') ?: $request->get('hub.mode');
            $token = $request->query('hub.verify_token') ?: $request->get('hub.verify_token');
            $challenge = $request->query('hub.challenge') ?: $request->get('hub.challenge');
        }

        // Log only in debug environments if necessary
        if (config('app.debug')) {
            Log::debug('Webhook verify params', ['mode' => $mode, 'token' => $token, 'challenge' => $challenge]);
        }

        if ($mode === 'subscribe' && $token && $token === config('services.meta.webhook_verify_token')) {
            return response()->json($challenge, 200);
        }

        return response()->json(['error' => 'invalid_verify_token'], 403);
    }

    // Receive POST webhooks: validate signature and dispatch
    public function receive(Request $request): JsonResponse
    {
        $raw = $request->getContent();
        $signature = $request->header('x-hub-signature-256') ?? $request->header('x-hub-signature');

        if (! $this->validateSignature($raw, $signature)) {
            return response()->json(['error' => 'invalid_signature'], 403);
        }

        $payload = $request->json()->all();
        // Let the service process the payload — it may log, dispatch jobs, etc.
        $this->service->process($payload);

        return response()->json(['status' => 'ok'], 200);
    }

    protected function validateSignature(string $raw, ?string $signature): bool
    {
        if (! $signature) {
            return false;
        }

        // Support both sha1 and sha256 signatures
        // Header example: 'sha1=abcd' or 'sha256=abcde'
        if (str_starts_with($signature, 'sha256=')) {
            $expected = 'sha256='.hash_hmac('sha256', $raw, config('services.meta.app_secret'));

            return hash_equals($expected, $signature);
        }

        if (str_starts_with($signature, 'sha1=')) {
            $expected = 'sha1='.hash_hmac('sha1', $raw, config('services.meta.app_secret'));

            return hash_equals($expected, $signature);
        }

        return false;
    }
}
