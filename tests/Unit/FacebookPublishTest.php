<?php

namespace Tests\Unit;

use App\Services\SocialMedia\MetaGraphService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class FacebookPublishTest extends TestCase
{
    public function test_publish_facebook_photo_uses_photos_endpoint()
    {
        config(['services.meta.page_access_token' => 'PAGE_TOKEN']);
        $apiVersion = config('services.meta.api_version') ?: 'v24.0';

        Http::fake([
            "https://graph.facebook.com/{$apiVersion}/*/photos" => Http::response(['id' => 'PHOTO_ID'], 200),
        ]);

        $service = new MetaGraphService;
        $resp = $service->publishFacebookPost('819795971219628', 'Caption text', 'https://example.com/photo.jpg');

        Http::assertSent(function ($request) use ($apiVersion) {
            return str_contains($request->url(), "/{$apiVersion}/") && str_contains($request->url(), '/photos');
        });

        $this->assertEquals('PHOTO_ID', data_get($resp, 'id'));
    }
}
