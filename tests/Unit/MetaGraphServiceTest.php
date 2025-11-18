<?php

namespace Tests\Unit;

use App\Services\SocialMedia\MetaGraphService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class MetaGraphServiceTest extends TestCase
{
    public function test_send_whatsapp_uses_page_access_token_when_not_passed()
    {
        config(['services.meta.page_access_token' => 'PAGE_TOKEN']);

        $apiVersion = config('services.meta.api_version') ?: 'v24.0';
        Http::fake([
            "https://graph.facebook.com/{$apiVersion}/*/messages" => Http::response(['messages' => [['id' => 'MSG_ID']]], 200),
        ]);

        $service = new MetaGraphService;
        $service->sendWhatsappMessage('111', '51999999999', 'Hello');

        Http::assertSent(function ($request) use ($apiVersion) {
            return str_contains($request->url(), "/{$apiVersion}/")
                && $request->header('Authorization') === ['Bearer PAGE_TOKEN'];
        });
    }

    public function test_publish_instagram_uses_ig_access_token_when_not_passed()
    {
        config(['services.meta.ig_access_token' => 'IG_TOKEN']);

        // fake both requests for instagram create and publish
        $apiVersion = config('services.meta.api_version') ?: 'v24.0';
        Http::fake([
            "https://graph.facebook.com/{$apiVersion}/24620739954263006/media" => Http::response(['id' => 'CONTAINER_ID'], 200),
            "https://graph.facebook.com/{$apiVersion}/24620739954263006/media_publish" => Http::response(['id' => 'PUBLISHED_ID'], 200),
        ]);

        $service = new MetaGraphService;
    $service->publishInstagramImage('24620739954263006', 'https://example.com/photo.jpg', 'Caption');

        Http::assertSent(function ($request) use ($apiVersion) {
            // request body is JSON; decode and check access_token
            $data = json_decode($request->body(), true);

            return str_contains($request->url(), "/{$apiVersion}/")
                && data_get($data, 'access_token') === 'IG_TOKEN';
        });
    }
}
