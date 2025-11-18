<?php

namespace App\Http\Controllers\Api\V1\Marketing;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Marketing\PublishFacebookRequest;
use App\Http\Requests\Api\V1\Marketing\PublishInstagramRequest;
use App\Services\SocialMedia\MetaGraphService;
use Illuminate\Http\JsonResponse;

class SocialMediaController extends Controller
{
    public function __construct(protected MetaGraphService $metaService) {}

    public function publishToFacebook(PublishFacebookRequest $request): JsonResponse
    {
        $payload = $request->validated();
        $pageId = $payload['page_id'] ?? config('services.meta.page_id') ?: env('META_PAGE_ID');
        $resp = $this->metaService->publishFacebookPost(
            $pageId,
            $payload['message'] ?? '',
            $payload['image_url'] ?? null,
            $payload['link'] ?? null,
            $payload['access_token'] ?? null
        );

        return response()->json($resp);
    }

    public function publishToInstagram(PublishInstagramRequest $request): JsonResponse
    {
        $payload = $request->validated();
        $igUserId = $payload['ig_user_id'] ?? config('services.meta.ig_user_id') ?: env('META_IG_USER_ID');
        $resp = $this->metaService->publishInstagramImage(
            $igUserId,
            $payload['image_url'],
            $payload['caption'] ?? '',
            $payload['access_token'] ?? null
        );

        return response()->json($resp);
    }
}
