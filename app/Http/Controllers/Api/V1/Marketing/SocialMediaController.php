<?php

namespace App\Http\Controllers\Api\V1\Marketing;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Marketing\PublishFacebookRequest;
use App\Http\Requests\Api\V1\Marketing\PublishInstagramRequest;
use App\Services\SocialMedia\MetaGraphService;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\JsonResponse;

class SocialMediaController extends Controller
{
    public function __construct(protected MetaGraphService $metaService) {}

    public function publishToFacebook(PublishFacebookRequest $request): JsonResponse
    {
        $payload = $request->validated();
        $pageId = $payload['page_id'] ?? config('services.meta.page_id') ?: env('META_PAGE_ID');
        // If an image or video file was uploaded, store it and use its public URL
        if ($request->hasFile('image')) {
            $file = $request->file('image');
            // store file to a temp location then upload via local file attachment
            $path = $file->store('uploads', 'public');
            $localPath = storage_path('app/public/'.$path);
            $resp = $this->metaService->publishFacebookPhotoFromLocalFile(
                $pageId,
                $localPath,
                $payload['message'] ?? '',
                $payload['access_token'] ?? null
            );
            // Clean up the stored file
            @unlink($localPath);
        } elseif ($request->hasFile('video')) {
            $file = $request->file('video');
            $path = $file->storePublicly('uploads', 'public');
            $videoPath = storage_path('app/public/'.$path);
            $resp = $this->metaService->publishFacebookVideo(
                $pageId,
                $videoPath,
                $payload['message'] ?? '',
                $payload['access_token'] ?? null
            );
            @unlink($videoPath);
        } else {
            $resp = $this->metaService->publishFacebookPost(
                $pageId,
                $payload['message'] ?? '',
                $payload['image_url'] ?? null,
                $payload['link'] ?? null,
                $payload['access_token'] ?? null
            );
        }

        return response()->json($resp);
    }

    public function publishToInstagram(PublishInstagramRequest $request): JsonResponse
    {
        $payload = $request->validated();
        $igUserId = $payload['ig_user_id'] ?? config('services.meta.ig_user_id') ?: env('META_IG_USER_ID');
        if ($request->hasFile('image')) {
            $file = $request->file('image');
            $path = $file->storePublicly('uploads', 'public');
            $imageUrl = Storage::disk('public')->url($path);
            $resp = $this->metaService->publishInstagramImage(
                $igUserId,
                $imageUrl,
                $payload['caption'] ?? '',
                $payload['access_token'] ?? null
            );
        } else {
            $resp = $this->metaService->publishInstagramImage(
                $igUserId,
                $payload['image_url'],
                $payload['caption'] ?? '',
                $payload['access_token'] ?? null
            );
        }

        return response()->json($resp);
    }
}
