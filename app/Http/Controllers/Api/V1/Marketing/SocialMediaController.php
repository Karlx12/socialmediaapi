<?php

namespace App\Http\Controllers\Api\V1\Marketing;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Marketing\PublishFacebookRequest;
use App\Http\Requests\Api\V1\Marketing\PublishInstagramRequest;
use App\Services\SocialMedia\MetaGraphService;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\JsonResponse;
use IncadevUns\CoreDomain\Models\Post;

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

        // Check if the API call returned a structured error and handle it gracefully
        if (isset($resp['error'])) {
            logger()->error('MetaGraphService error calling Facebook', ['resp' => $resp, 'campaign_id' => $payload['campaign_id'] ?? null, 'user_id' => auth()->id()]);
            $isHttp = ($resp['error'] === 'http_error' || $resp['error'] === 'exception');
            $status = $isHttp ? 502 : 400;
            return response()->json(['error' => 'Meta API error', 'details' => $resp], $status);
        }

        if (!isset($resp['id'])) {
            logger()->warning('MetaGraphService returned unexpected response body (no id)', ['resp' => $resp, 'campaign_id' => $payload['campaign_id'] ?? null]);
            return response()->json(['error' => 'Invalid response from Meta API', 'response' => $resp], 502);
        }

        // Check if the API call returned a structured error and handle it gracefully
        if (isset($resp['error'])) {
            logger()->error('MetaGraphService error calling Instagram', ['resp' => $resp, 'campaign_id' => $payload['campaign_id'] ?? null, 'user_id' => auth()->id()]);
            $isHttp = ($resp['error'] === 'http_error' || $resp['error'] === 'exception');
            $status = $isHttp ? 502 : 400;
            return response()->json(['error' => 'Meta API error', 'details' => $resp], $status);
        }

        if (!isset($resp['id'])) {
            logger()->warning('MetaGraphService returned unexpected response body (no id)', ['resp' => $resp, 'campaign_id' => $payload['campaign_id'] ?? null]);
            return response()->json(['error' => 'Invalid response from Meta API', 'response' => $resp], 502);
        }
        $contentType = 'text';
        if ($request->hasFile('image')) {
            $contentType = 'image';
        } elseif ($request->hasFile('video')) {
            $contentType = 'video';
        }

        try {
            $post = Post::create([
                'campaign_id' => $payload['campaign_id'] ?? null,
                'meta_post_id' => $resp['id'],
                'title' => substr($payload['message'] ?? 'Facebook Post', 0, 255),
                'platform' => 'facebook',
                'content' => $payload['message'] ?? null,
                'content_type' => $contentType,
                'image_path' => $request->hasFile('image') ? $path : null,
                'link_url' => $payload['link'] ?? null,
                'status' => 'published',
                'published_at' => now(),
                // created_by can be null when the call was unauthenticated (local dev)
                'created_by' => auth()->id() ?? null,
            ]);
        } catch (\Illuminate\Database\QueryException $e) {
            if ($e->getCode() == 23000 && str_contains($e->getMessage(), 'posts_campaign_id_foreign')) {
                return response()->json(['error' => 'Campaign not found', 'code' => 'CAMPAIGN_NOT_FOUND'], 400);
            }
            throw $e;
        }

        return response()->json([
            'meta_post_id' => $resp['id'],
            'post_id' => $post->id,
            'data' => $resp,
        ]);
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

        // Guardar el post en la base de datos
        $contentType = 'image'; // Instagram posts are typically images or videos, but this method handles images
        $imagePath = null;
        if ($request->hasFile('image')) {
            $imagePath = $path;
        }

        try {
            $post = Post::create([
                'campaign_id' => $payload['campaign_id'] ?? null,
                'meta_post_id' => $resp['id'],
                'title' => substr($payload['caption'] ?? 'Instagram Post', 0, 255),
                'platform' => 'instagram',
                'content' => $payload['caption'] ?? null,
                'content_type' => $contentType,
                'image_path' => $imagePath,
                'status' => 'published',
                'published_at' => now(),
                'created_by' => auth()->id() ?? null,
            ]);
        } catch (\Illuminate\Database\QueryException $e) {
            if ($e->getCode() == 23000 && str_contains($e->getMessage(), 'posts_campaign_id_foreign')) {
                return response()->json(['error' => 'Campaign not found', 'code' => 'CAMPAIGN_NOT_FOUND'], 400);
            }
            throw $e;
        }

        return response()->json([
            'meta_post_id' => $resp['id'],
            'post_id' => $post->id,
            'data' => $resp,
        ]);
    }
}
