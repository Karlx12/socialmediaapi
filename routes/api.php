<?php

use App\Http\Controllers\Api\V1\Marketing\SocialChatController;
use App\Http\Controllers\Api\V1\Marketing\SocialMediaController;
use App\Http\Controllers\Api\V1\Marketing\WebhookController;
use Illuminate\Support\Facades\Route;

// Webhook endpoints and authenticated socialmedia endpoints under /api/socialmedia
Route::prefix('socialmedia')->group(function () {
    // Webhooks (public)
    Route::get('webhook', [WebhookController::class, 'verify']);
    Route::post('webhook', [WebhookController::class, 'receive']);

    // Public API (no authentication required)
    Route::post('posts/facebook', [SocialMediaController::class, 'publishToFacebook']);
    Route::post('posts/instagram', [SocialMediaController::class, 'publishToInstagram']);
    Route::post('chats/send', [SocialChatController::class, 'sendMessage']);
});
