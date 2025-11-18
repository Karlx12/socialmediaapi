<?php

namespace Tests\Feature;

use App\Services\SocialMedia\MetaGraphService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class SocialMediaApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_publish_to_facebook_routes_and_service_called()
    {
        $this->withoutExceptionHandling();

        $mock = \Mockery::mock(MetaGraphService::class);
        $mock->shouldReceive('publishFacebookPost')
            ->once()
            ->andReturn(['id' => '1234']);
        $this->app->instance(MetaGraphService::class, $mock);

        // Create a user and act as
        $user = \App\Models\User::factory()->create();
        $token = $user->createToken('test')->plainTextToken;

        $resp = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/marketing/socialmedia/posts/facebook', [
                'message' => 'test N° 1',
                'page_id' => '123',
                'access_token' => 'fake',
            ]);

        $resp->assertStatus(200);
        $resp->assertJson(['id' => '1234']);
    }

    // Format validation removed: message pattern enforcement is not required by default.

    public function test_send_whatsapp_message_routes_and_service_called()
    {
        $mock = \Mockery::mock(MetaGraphService::class);
        $mock->shouldReceive('sendWhatsappMessage')
            ->once()
            ->andReturn(['messages' => [['id' => 'g123']]]);
        $this->app->instance(MetaGraphService::class, $mock);

        $user = \App\Models\User::factory()->create();
        $token = $user->createToken('test')->plainTextToken;

        $resp = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/marketing/socialmedia/chats/send', [
                'platform' => 'whatsapp',
                'to' => '51999999999',
                'message' => 'Hi!',
                'phone_number_id' => '111',
                'access_token' => 'fake',
            ]);

        $resp->assertStatus(200);
        $resp->assertJson(['messages' => [['id' => 'g123']]]);
    }

    public function test_send_whatsapp_template_routes_and_service_called()
    {
        $mock = \Mockery::mock(MetaGraphService::class);
        $mock->shouldReceive('sendWhatsappMessage')
            ->once()
            ->andReturn(['messages' => [['id' => 'g123', 'message_status' => 'accepted']]]);
        $this->app->instance(MetaGraphService::class, $mock);

        $user = \App\Models\User::factory()->create();
        $token = $user->createToken('test')->plainTextToken;

        $resp = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/marketing/socialmedia/chats/send', [
                'platform' => 'whatsapp',
                'to' => '51999999999',
                'template' => [
                    'name' => 'hello_world',
                    'language' => ['code' => 'en_US']
                ],
                'phone_number_id' => '111',
                'access_token' => 'fake',
            ]);

        $resp->assertStatus(200);
        $resp->assertJson(['messages' => [['id' => 'g123', 'message_status' => 'accepted']]]);
    }

    public function test_publish_to_facebook_with_uploaded_image_stores_and_service_called()
    {
        Storage::fake('public');

        $mock = \Mockery::mock(MetaGraphService::class);
        $mock->shouldReceive('publishFacebookPhotoFromLocalFile')
            ->once()
            ->andReturn(['id' => '1234']);
        $this->app->instance(MetaGraphService::class, $mock);

        $user = \App\Models\User::factory()->create();
        $token = $user->createToken('test')->plainTextToken;

        $file = UploadedFile::fake()->image('social.png');

        $resp = $this->withHeader('Authorization', "Bearer {$token}")
            ->post('/api/v1/marketing/socialmedia/posts/facebook', [
                'image' => $file,
                'message' => 'Prueba con imagen',
                'page_id' => '123',
                'access_token' => 'fake',
            ]);

        $resp->assertStatus(200);
        Storage::disk('public')->assertExists('uploads/' . $file->hashName());
    }
}
