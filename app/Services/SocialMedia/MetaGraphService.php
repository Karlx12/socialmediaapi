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
        try {
            $resp = Http::asForm()->timeout(15)->post($url, $payload);
            $data = $resp->json();
            if ($resp->successful()) return $data;
            return ['error' => 'http_error', 'status' => $resp->status(), 'details' => $data];
        } catch (\Throwable $e) {
            // Ensure we return a structured error instead of letting exceptions bubble up
            return ['error' => 'exception', 'message' => $e->getMessage()];
        }
    }

    public function uploadPhotoToFacebook(string $pageId, string $imageUrl, string $accessToken): string
    {
        // Upload photo to page to get media id
        try {
            $resp = Http::timeout(15)->post("{$this->graphUrl}/{$this->apiVersion}/{$pageId}/photos", [
            'url' => $imageUrl,
            'published' => false,
            'access_token' => $accessToken,
        ]);
            $data = $resp->json();
            if (!empty($data['id'])) return $data['id'];
            return '';
        } catch (\Throwable $e) {
            return '';
        }
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
        try {
            $resp = Http::asForm()->timeout(30)->post($url, $payload);
            $data = $resp->json();
            if ($resp->successful()) return $data;
            return ['error' => 'http_error', 'status' => $resp->status(), 'details' => $data];
        } catch (\Throwable $e) {
            return ['error' => 'exception', 'message' => $e->getMessage()];
        }
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
        try {
            // Use multipart attaching the video file as 'source'
            $resp = Http::timeout(60)
                ->attach('source', file_get_contents($localFilePath), basename($localFilePath))
                ->asMultipart()
                ->post($url, [
                    'description' => $description,
                    'access_token' => $accessToken,
                    'published' => $published ? 'true' : 'false',
                ]);
            $data = $resp->json();
            if ($resp->successful()) return $data;
            return ['error' => 'http_error', 'status' => $resp->status(), 'details' => $data];
        } catch (\Throwable $e) {
            return ['error' => 'exception', 'message' => $e->getMessage()];
        }
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

        try {
            $createResp = Http::timeout(30)->post("{$this->graphUrl}/{$this->apiVersion}/{$igUserId}/media", $payload);
            $createData = $createResp->json();
        } catch (\Throwable $e) {
            return ['error' => 'exception', 'message' => $e->getMessage()];
        }
        if (empty($createData['id'])) {
            return ['error' => 'media_creation_failed', 'details' => $createData];
        }
        // Publish the IG media container
        $publishPayload = [
            'creation_id' => $createData['id'],
            'access_token' => $accessToken,
        ];
        try {
            $publishResp = Http::timeout(30)->post("{$this->graphUrl}/{$this->apiVersion}/{$igUserId}/media_publish", $publishPayload);
            $data = $publishResp->json();
            if ($publishResp->successful()) return $data;
            return ['error' => 'http_error', 'status' => $publishResp->status(), 'details' => $data];
        } catch (\Throwable $e) {
            return ['error' => 'exception', 'message' => $e->getMessage()];
        }
    }

    /**
     * Send a WhatsApp message via the Graph API.
     * $messageOrPayload can be a string (text body) or an array (template payload).
     */
    public function sendWhatsappMessage(string $phoneNumberId, string $to, $messageOrPayload, ?string $accessToken = null): array
    {
        // WhatsApp uses the page access token by default
        $accessToken ??= config('services.meta.page_access_token') ?: env('META_PAGE_ACCESS_TOKEN');
        if (! $accessToken) {
            return ['error' => 'whatsapp_token_missing', 'message' => 'Whatsapp token is required'];
        }
        $url = "https://graph.facebook.com/{$this->apiVersion}/{$phoneNumberId}/messages";
        if (is_string($messageOrPayload)) {
            // Text message
            $payload = [
                'messaging_product' => 'whatsapp',
                'to' => $to,
                'type' => 'text',
                'text' => ['body' => $messageOrPayload],
            ];
        } elseif (is_array($messageOrPayload) && isset($messageOrPayload['template'])) {
            // Template message
            $payload = [
                'messaging_product' => 'whatsapp',
                'to' => $to,
                'type' => 'template',
                'template' => $messageOrPayload['template'],
            ];
        } else {
            // If payload is array with message key
            if (is_array($messageOrPayload) && isset($messageOrPayload['message'])) {
                $payload = [
                    'messaging_product' => 'whatsapp',
                    'to' => $to,
                    'type' => 'text',
                    'text' => ['body' => $messageOrPayload['message']],
                ];
            } else {
                return ['error' => 'invalid_whatsapp_payload', 'message' => 'Invalid payload for WhatsApp message.'];
            }
        }

        try {
            $resp = Http::withToken($accessToken)->timeout(20)->post($url, $payload);
            $data = $resp->json();
            if ($resp->successful()) return $data;
            return ['error' => 'http_error', 'status' => $resp->status(), 'details' => $data];
        } catch (\Throwable $e) {
            return ['error' => 'exception', 'message' => $e->getMessage()];
        }
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
        try {
            $resp = Http::post($url, $payload);
            $data = $resp->json();
            if ($resp->successful()) return $data;
            return ['error' => 'http_error', 'status' => $resp->status(), 'details' => $data];
        } catch (\Throwable $e) {
            return ['error' => 'exception', 'message' => $e->getMessage()];
        }
    }
}
