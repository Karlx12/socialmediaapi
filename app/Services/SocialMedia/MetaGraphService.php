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
        // IG uses the page access token by default (same as Facebook/WhatsApp)
        $accessToken ??= config('services.meta.page_access_token') ?: env('META_PAGE_ACCESS_TOKEN');
        if (! $accessToken) {
            return ['error' => 'ig_access_token_missing', 'message' => 'Instagram access token is required'];
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

        // Publish container
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
