<?php

use App\Http\Controllers\Api\V1\Marketing\SocialChatController;
use App\Http\Controllers\Api\V1\Marketing\SocialMediaController;
use App\Http\Controllers\Api\V1\Marketing\WebhookController;
use Illuminate\Support\Facades\Route;

// Webhook endpoints from Meta should be public (they verify via verify token / signature)
// Note: this file already gets the `api` prefix automatically so avoid repeating it
Route::prefix('v1/marketing/socialmedia')->group(function () {
    Route::get('webhook', [WebhookController::class, 'verify']);
    Route::post('webhook', [WebhookController::class, 'receive']);
});

Route::prefix('v1/marketing/socialmedia')->middleware('auth:sanctum')->group(function () {
    Route::post('posts/facebook', [SocialMediaController::class, 'publishToFacebook']);
    Route::post('posts/instagram', [SocialMediaController::class, 'publishToInstagram']);

    Route::post('chats/send', [SocialChatController::class, 'sendMessage']);
});
