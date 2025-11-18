<?php

namespace App\Services\SocialMedia;

use Illuminate\Support\Facades\Http;

class MetaGraphService
{
    protected string $graphUrl = 'https://graph.facebook.com';

    protected string $apiVersion = 'v24.0';

    public function __construct()
    {
        $this->apiVersion = config('services.meta.api_version') ?: env('META_API_VERSION', $this->apiVersion);
    }

    public function publishFacebookPost(string $pageId, string $message, ?string $imageUrl = null, ?string $link = null, ?string $accessToken = null, bool $published = true): array
    {
        $accessToken ??= config('services.meta.page_access_token') ?: env('META_PAGE_ACCESS_TOKEN');
        if (! $accessToken) {
            return ['error' => 'page_access_token_missing', 'message' => 'Page access token is required'];
        }

        $payload = [
            'message' => $message,
            'access_token' => $accessToken,
        ];

        if ($link) {
            $payload['link'] = $link;
        }

        // Prefer using the /photos endpoint for photos to ensure native photo posts
        if ($imageUrl && ! $link) {
            return $this->publishFacebookPhoto($pageId, $imageUrl, $message, $accessToken, $published);
        }

        $url = "{$this->graphUrl}/{$this->apiVersion}/{$pageId}/feed";
        $resp = Http::asForm()->post($url, $payload);

        return $resp->json();
    }

    public function uploadPhotoToFacebook(string $pageId, string $imageUrl, string $accessToken): string
    {
        // Upload photo to page to get media id
        $resp = Http::post("{$this->graphUrl}/{$this->apiVersion}/{$pageId}/photos", [
            'url' => $imageUrl,
            'published' => false,
            'access_token' => $accessToken,
        ]);
        $data = $resp->json();

        return $data['id'] ?? '';
    }

    /**
     * Publish a photo to a Facebook page using the /photos endpoint.
     * This creates a native photo post and can optionally be unpublished.
     */
    public function publishFacebookPhoto(string $pageId, string $imageUrl, string $caption = '', ?string $accessToken = null, bool $published = true): array
    {
        $accessToken ??= config('services.meta.page_access_token') ?: env('META_PAGE_ACCESS_TOKEN');
        if (! $accessToken) {
            return ['error' => 'page_access_token_missing', 'message' => 'Page access token is required'];
        }

        $payload = [
            'url' => $imageUrl,
            'caption' => $caption,
            'access_token' => $accessToken,
            'published' => $published,
        ];

        $url = "{$this->graphUrl}/{$this->apiVersion}/{$pageId}/photos";
        $resp = Http::asForm()->post($url, $payload);

        return $resp->json();
    }

    /**
     * Publish a photo by uploading local file content to the Facebook Page /photos endpoint.
     */
    public function publishFacebookPhotoFromLocalFile(string $pageId, string $localFilePath, string $caption = '', ?string $accessToken = null, bool $published = true): array
    {
        $accessToken ??= config('services.meta.page_access_token') ?: env('META_PAGE_ACCESS_TOKEN');
        if (! $accessToken) {
            return ['error' => 'page_access_token_missing', 'message' => 'Page access token is required'];
        }

        if (! file_exists($localFilePath)) {
            return ['error' => 'file_not_found', 'message' => 'Local photo file not found'];
        }

        $url = "{$this->graphUrl}/{$this->apiVersion}/{$pageId}/photos";
        $resp = Http::attach('source', file_get_contents($localFilePath), basename($localFilePath))
            ->asMultipart()
            ->post($url, [
                'caption' => $caption,
                'access_token' => $accessToken,
                'published' => $published ? 'true' : 'false',
            ]);

        return $resp->json();
    }

    /**
     * Publish a video to a Facebook page using the /videos endpoint. Accepts a local file path.
     */
    public function publishFacebookVideo(string $pageId, string $localFilePath, string $description = '', ?string $accessToken = null, bool $published = true): array
    {
        $accessToken ??= config('services.meta.page_access_token') ?: env('META_PAGE_ACCESS_TOKEN');
        if (! $accessToken) {
            return ['error' => 'page_access_token_missing', 'message' => 'Page access token is required'];
        }

        if (! file_exists($localFilePath)) {
            return ['error' => 'file_not_found', 'message' => 'Local video file not found'];
        }

        $url = "{$this->graphUrl}/{$this->apiVersion}/{$pageId}/videos";

        // Use multipart attaching the video file as 'source'
        $resp = Http::attach('source', file_get_contents($localFilePath), basename($localFilePath))
            ->asMultipart()
            ->post($url, [
                'description' => $description,
                'access_token' => $accessToken,
                'published' => $published ? 'true' : 'false',
            ]);

        return $resp->json();
    }

    public function publishInstagramImage(
        string $igUserId,
        string $imageUrl,
        string $caption,
        ?string $accessToken = null,
        string $mediaType = 'IMAGE',
        ?string $coverUrl = null,
        bool $shareToFeed = false,
        ?string $altText = null,
        array $children = []
    ): array {
        // Must use a dedicated Instagram access token only
        if (! $accessToken) {
            $accessToken = config('services.meta.ig_access_token') ?: env('META_IG_ACCESS_TOKEN');
        }
        if (! $accessToken) {
            return ['error' => 'ig_access_token_missing', 'message' => 'Instagram access token is required'];
        }

        // Use requested IG user id or fallback to configured IG user id from env
        $igUserId = $igUserId ?: (config('services.meta.ig_user_id') ?: env('META_IG_USER_ID'));
        if (! $igUserId) {
            return ['error' => 'ig_user_id_missing', 'message' => 'Instagram user id is required'];
        }

        // Create media container
        $payload = [
            'caption' => $caption,
            'access_token' => $accessToken,
        ];
        if ($mediaType === 'IMAGE' || $mediaType === 'STORIES') {
            $payload['image_url'] = $imageUrl;
        } else {
            $payload['video_url'] = $imageUrl;
        }
        $payload['media_type'] = $mediaType;
        if ($coverUrl) {
            $payload['cover_url'] = $coverUrl;
        }
        if ($shareToFeed) {
            $payload['share_to_feed'] = true;
        }
        if ($altText) {
            $payload['alt_text'] = $altText;
        }
        if (! empty($children)) {
            $payload['children'] = json_encode($children);
        }

        $createResp = Http::post("{$this->graphUrl}/{$this->apiVersion}/{$igUserId}/media", $payload);
        $createData = $createResp->json();
        if (empty($createData['id'])) {
            return ['error' => 'media_creation_failed', 'details' => $createData];
        }
        // Publish the IG media container
        $publishPayload = [
            'creation_id' => $createData['id'],
            'access_token' => $accessToken,
        ];
        $publishResp = Http::post("{$this->graphUrl}/{$this->apiVersion}/{$igUserId}/media_publish", $publishPayload);

        return $publishResp->json();
    }

    public function sendWhatsappMessage(string $phoneNumberId, string $to, string $message, ?string $accessToken = null): array
    {
        // WhatsApp uses the page access token by default
        $accessToken ??= config('services.meta.page_access_token') ?: env('META_PAGE_ACCESS_TOKEN');
        if (! $accessToken) {
            return ['error' => 'whatsapp_token_missing', 'message' => 'Whatsapp token is required'];
        }

        $url = "https://graph.facebook.com/{$this->apiVersion}/{$phoneNumberId}/messages";
        $payload = [
            'messaging_product' => 'whatsapp',
            'to' => $to,
            'type' => 'text',
            'text' => ['body' => $message],
        ];

        $resp = Http::withToken($accessToken)->post($url, $payload);

        return $resp->json();
    }

    public function sendMessengerMessage(string $pageAccessToken, string $recipientId, string $message): array
    {
        $url = "https://graph.facebook.com/{$this->apiVersion}/me/messages?access_token={$pageAccessToken}";
        $payload = [
            'recipient' => ['id' => $recipientId],
            'message' => ['text' => $message],
        ];
        $resp = Http::post($url, $payload);

        return $resp->json();
    }

    public function sendInstagramMessage(string $igUserId, string $recipientId, string $message, ?string $accessToken = null): array
    {
        // Instagram Messaging is part of the Messenger API for Instagram
        $accessToken ??= config('services.meta.page_access_token') ?: env('META_PAGE_ACCESS_TOKEN');
        if (! $accessToken) {
            return ['error' => 'instagram_token_missing', 'message' => 'Instagram messaging needs a page access token'];
        }
        $url = "https://graph.facebook.com/{$this->apiVersion}/{$igUserId}/messages";
        $payload = [
            'recipient' => ['id' => $recipientId],
            'message' => ['text' => $message],
            'access_token' => $accessToken,
        ];
        $resp = Http::post($url, $payload);

        return $resp->json();
    }
}
