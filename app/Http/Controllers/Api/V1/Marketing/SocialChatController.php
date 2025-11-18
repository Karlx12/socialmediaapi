<?php

namespace App\Http\Controllers\Api\V1\Marketing;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Marketing\SendChatMessageRequest;
use App\Services\SocialMedia\MetaGraphService;
use Illuminate\Http\JsonResponse;

class SocialChatController extends Controller
{
    public function __construct(protected MetaGraphService $metaService) {}

    public function sendMessage(SendChatMessageRequest $request): JsonResponse
    {
        $payload = $request->validated();
        $platform = $payload['platform'];
        $to = $payload['to'];
        $message = $payload['message'];

        if ($platform === 'whatsapp') {
            $phoneNumberId = $payload['phone_number_id'] ?? config('services.meta.whatsapp_phone_number_id') ?: env('META_WHATSAPP_NUMBER_ID');
            $resp = $this->metaService->sendWhatsappMessage($phoneNumberId, $to, $message, $payload['access_token'] ?? null);
        } elseif ($platform === 'messenger') {
            $pageAccessToken = $payload['access_token'] ?? config('services.meta.page_access_token') ?: env('META_PAGE_ACCESS_TOKEN');
            $resp = $this->metaService->sendMessengerMessage($pageAccessToken, $to, $message);
        } else { // instagram
            $igUserId = $payload['ig_user_id'] ?? config('services.meta.ig_user_id') ?: env('META_IG_USER_ID');
            $resp = $this->metaService->sendInstagramMessage($igUserId, $to, $message, $payload['access_token'] ?? null);
        }

        return response()->json($resp);
    }
}
