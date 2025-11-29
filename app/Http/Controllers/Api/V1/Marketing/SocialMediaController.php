<?php

namespace App\Http\Controllers\Api\V1\Marketing;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Marketing\PublishFacebookRequest;
use App\Http\Requests\Api\V1\Marketing\PublishInstagramRequest;
use App\Services\SocialMedia\MetaGraphService;
use Illuminate\Support\Facades\Http as HttpFacade;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\JsonResponse;
use IncadevUns\CoreDomain\Models\Post;

class SocialMediaController extends Controller
{
    public function __construct(protected MetaGraphService $metaService) {}

    public function publishToFacebook(PublishFacebookRequest $request): JsonResponse
    {
        // Preflight check: ensure we have a configured page access token to avoid ambiguous upstream errors
        if (!config('services.meta.page_access_token') && !env('META_PAGE_ACCESS_TOKEN')) {
            logger()->warning('Attempt to publish to Facebook but META_PAGE_ACCESS_TOKEN is not configured');
            return response()->json(['error' => 'Social Media API misconfigured: META_PAGE_ACCESS_TOKEN is missing or invalid'], 502);
        }

        $payload = $request->validated();
        $post = null;
        // If the request references an existing Post model, load it and use its values
        if (! empty($payload['post_id'])) {
            $post = Post::find($payload['post_id']);
            if (! $post) {
                return response()->json(['error' => 'Post not found', 'code' => 'POST_NOT_FOUND'], 404);
            }
            // Build payload from Post model if values are not present
            $payload['message'] = $payload['message'] ?? $post->content ?? null;
            $payload['image_url'] = $payload['image_url'] ?? ($post->image_path ? Storage::disk('public')->url($post->image_path) : null);
            $payload['link'] = $payload['link'] ?? $post->link_url ?? null;
            $payload['campaign_id'] = $payload['campaign_id'] ?? $post->campaign_id ?? null;
        }
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

        // If the API call returned a structured error, handle specific cases and return meaningful status
        if (isset($resp['error'])) {
            logger()->error('MetaGraphService error calling Facebook', ['resp' => $resp, 'campaign_id' => $payload['campaign_id'] ?? null, 'user_id' => auth()->id()]);
            // If the Meta API indicates the page access token is missing, return 502 with clearer message to admins
            if (($resp['error'] ?? '') === 'page_access_token_missing' || (isset($resp['details']['error']) && $resp['details']['error'] === 'page_access_token_missing')) {
                return response()->json(['error' => 'Social Media API misconfigured: META_PAGE_ACCESS_TOKEN is missing or invalid', 'details' => $resp], 502);
            }
            $isHttp = in_array($resp['error'], ['http_error', 'exception'], true);
            $status = $isHttp ? 502 : 400; // 502 for upstream HTTP or exception errors, 400 for other meta errors
            return response()->json(['error' => 'Meta API error', 'details' => $resp], $status);
        }

        // Graph API should return an id for published objects — otherwise it's an unexpected response
        if (! isset($resp['id'])) {
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
            if ($post) {
                // Update existing Post with publish attributes
                $post->meta_post_id = $resp['id'];
                $post->status = 'published';
                $post->published_at = now();
                $post->save();
            } else {
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
            }
        } catch (\Illuminate\Database\QueryException $e) {
            // MySQL / SQLite return 23000 for unique/key violations and foreign key errors
            // Handle foreign key violation for campaigns
            if ($e->getCode() == 23000 && str_contains($e->getMessage(), 'posts_campaign_id_foreign')) {
                return response()->json(['error' => 'Campaign not found', 'code' => 'CAMPAIGN_NOT_FOUND'], 404);
            }
            // Handle duplicate meta_post_id unique violation
            if ($e->getCode() == 23000 && (str_contains($e->getMessage(), 'posts_meta_post_id_unique') || str_contains($e->getMessage(), 'UNIQUE') || str_contains($e->getMessage(), 'duplicate'))) {
                // If duplicate meta_post_id detected in DB, update the existing record and remove the draft (if any)
                $existing = Post::where('meta_post_id', $resp['id'])->first();
                if ($existing) {
                    $existing->status = 'published';
                    $existing->published_at = now();
                    $existing->save();
                    if ($post && $post->id !== $existing->id) {
                        try {
                            $post->delete();
                        } catch (\Throwable $t) {
                            // ignore
                        }
                    }
                    return response()->json(['meta_post_id' => $resp['id'], 'post_id' => $existing->id, 'data' => $resp]);
                }
                return response()->json(['error' => 'Duplicate meta_post_id', 'code' => 'DUPLICATE_META_POST'], 409);
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
        // Preflight check: ensure we have IG user id and access token configured
        if (!config('services.meta.ig_access_token') && !env('META_IG_ACCESS_TOKEN') && !config('services.meta.page_access_token') && !env('META_PAGE_ACCESS_TOKEN')) {
            logger()->warning('Attempt to publish to Instagram but IG access token or page access token is not configured');
            return response()->json(['error' => 'Social Media API misconfigured: META_IG_ACCESS_TOKEN or META_PAGE_ACCESS_TOKEN is missing or invalid'], 502);
        }

        $payload = $request->validated();
        $post = null;
        if (! empty($payload['post_id'])) {
            $post = Post::find($payload['post_id']);
            if (! $post) {
                return response()->json(['error' => 'Post not found', 'code' => 'POST_NOT_FOUND'], 404);
            }
            if (! empty($post->meta_post_id)) {
                return response()->json(['error' => 'Post already published', 'code' => 'META_ALREADY_POSTED'], 409);
            }
            $payload['caption'] = $payload['caption'] ?? $post->content ?? null;
            $payload['image_url'] = $payload['image_url'] ?? ($post->image_path ? Storage::disk('public')->url($post->image_path) : null);
            $payload['campaign_id'] = $payload['campaign_id'] ?? $post->campaign_id ?? null;
        }
        $igUserId = $payload['ig_user_id'] ?? config('services.meta.ig_user_id') ?: env('META_IG_USER_ID');
        if ($request->hasFile('image')) {
            $file = $request->file('image');
            $path = $file->storePublicly('uploads', 'public');
            $imageUrl = Storage::disk('public')->url($path);
            // If uploaded file is not JPEG, try to convert it to JPEG to be compatible with IG
            $fileMime = $file->getMimeType() ?? $file->getClientMimeType();
            if (! $fileMime || stripos($fileMime, 'jpeg') === false) {
                // Attempt conversion
                try {
                    $fullPath = storage_path('app/public/' . $path);
                    if (function_exists('imagecreatefromstring')) {
                        $bytes = file_get_contents($fullPath);
                        $im = @imagecreatefromstring($bytes);
                        if ($im !== false) {
                            ob_start();
                            imagejpeg($im, null, 85);
                            imagedestroy($im);
                            $jpeg = ob_get_clean();
                            if ($jpeg !== false && strlen($jpeg) > 0) {
                                // Replace stored file content with JPEG
                                Storage::disk('public')->put($path, $jpeg);
                                $imageUrl = Storage::disk('public')->url($path);
                            } else {
                                return response()->json(['error' => 'Failed to convert uploaded image to JPEG'], 400);
                            }
                        } else {
                            return response()->json(['error' => 'Uploaded file is not a valid image'], 400);
                        }
                    } else {
                        return response()->json(['error' => 'Server does not support image conversion (missing GD)'], 500);
                    }
                } catch (\Throwable $e) {
                    return response()->json(['error' => 'Failed to process uploaded image', 'details' => $e->getMessage()], 500);
                }
            }
        } else {
            $imageUrl = $payload['image_url'] ?? null;
        }

        // Validate image URL: not localhost, reachable, and must be JPEG
        if (empty($imageUrl)) {
            return response()->json(['error' => 'Instagram posts require an image_url or an uploaded image file.'], 400);
        }
        if (str_contains($imageUrl, 'localhost') || str_contains($imageUrl, '127.0.0.1')) {
            return response()->json(['error' => 'Instagram API requires the image URL to be publicly accessible. The provided URL points to localhost; use ngrok or a public image URL.'], 400);
        }
        try {
            $headResp = HttpFacade::timeout(5)->head($imageUrl);
            if (! $headResp->ok()) {
                return response()->json(['error' => 'Provided image URL is not reachable by the server running the API. Ensure the URL is publicly accessible.'], 400);
            }
            $contentType = $headResp->header('Content-Type') ?? $headResp->header('content-type');
            if (! $contentType || stripos($contentType, 'jpeg') === false) {
                return response()->json(['error' => 'Instagram API requires JPEG images. Provide a publicly accessible JPEG image URL or upload a JPEG file.'], 400);
            }
        } catch (\Throwable $e) {
            return response()->json(['error' => 'Unable to reach image URL to validate it; ensure the URL is public and accessible.'], 400);
        }

        $resp = $this->metaService->publishInstagramImage(
            $igUserId,
            $imageUrl,
            $payload['caption'] ?? '',
            $payload['access_token'] ?? null
        );

        // Guardar el post en la base de datos
        $contentType = 'image'; // Instagram posts are typically images or videos, but this method handles images
        $imagePath = null;
        if ($request->hasFile('image')) {
            $imagePath = $path;
        }

        // Guard against errors coming back from the MetaGraphService
        if (isset($resp['error'])) {
            logger()->error('MetaGraphService error calling Instagram', ['resp' => $resp, 'campaign_id' => $payload['campaign_id'] ?? null, 'user_id' => auth()->id()]);
            if (($resp['error'] ?? '') === 'ig_access_token_missing' || (isset($resp['details']['error']) && $resp['details']['error'] === 'ig_access_token_missing')) {
                return response()->json(['error' => 'Social Media API misconfigured: META_IG_ACCESS_TOKEN is missing or invalid', 'details' => $resp], 502);
            }
            $isHttp = in_array($resp['error'], ['http_error', 'exception'], true);
            $status = $isHttp ? 502 : 400;
            return response()->json(['error' => 'Meta API error', 'details' => $resp], $status);
        }

        if (! isset($resp['id'])) {
            logger()->warning('MetaGraphService returned unexpected response body (no id)', ['resp' => $resp, 'campaign_id' => $payload['campaign_id'] ?? null]);
            return response()->json(['error' => 'Invalid response from Meta API', 'response' => $resp], 502);
        }

        try {
            if ($post) {
                $post->meta_post_id = $resp['id'];
                $post->status = 'published';
                $post->published_at = now();
                $post->save();
            } else {
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
            }
        } catch (\Illuminate\Database\QueryException $e) {
            if ($e->getCode() == 23000 && str_contains($e->getMessage(), 'posts_campaign_id_foreign')) {
                return response()->json(['error' => 'Campaign not found', 'code' => 'CAMPAIGN_NOT_FOUND'], 404);
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
