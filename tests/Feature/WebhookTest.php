<?php

namespace Tests\Feature;

use Tests\TestCase;

class WebhookTest extends TestCase
{
    public function test_verify_webhook_valid_token_returns_challenge()
    {
        config(['services.meta.webhook_verify_token' => 'MY_VERIFY_TOKEN']);

        $resp = $this->get('/api/socialmedia/webhook?hub[mode]=subscribe&hub[verify_token]=MY_VERIFY_TOKEN&hub[challenge]=CHAL');

        $resp->assertStatus(200);
        $this->assertEquals('CHAL', json_decode($resp->getContent(), true));
    }

    public function test_verify_webhook_invalid_token_returns_403()
    {
        config(['services.meta.webhook_verify_token' => 'MY_VERIFY_TOKEN']);

        $resp = $this->get('/api/socialmedia/webhook?hub[mode]=subscribe&hub[verify_token]=WRONG&hub[challenge]=CHAL');

        $resp->assertStatus(403);
    }

    public function test_receive_webhook_requires_valid_signature()
    {
        config(['services.meta.app_secret' => 'APP_SECRET']);

        $payload = ['object' => 'page', 'entry' => [['id' => 'PAGE_ID', 'changes' => [['value' => 'x']]]]];
        $raw = json_encode($payload);

        // invalid signature should be rejected
        $resp = $this->withHeaders(['x-hub-signature' => 'sha1=invalid'])->postJson('/api/socialmedia/webhook', $payload);
        $resp->assertStatus(403);

        // proper signature should be accepted
        $signature = 'sha1='.hash_hmac('sha1', $raw, config('services.meta.app_secret'));
        $resp = $this->withHeaders(['x-hub-signature' => $signature])->postJson('/api/socialmedia/webhook', $payload);
        $resp->assertStatus(200);
    }
}
