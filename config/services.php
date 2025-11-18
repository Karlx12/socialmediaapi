<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'meta' => [
        'page_id' => env('META_PAGE_ID'),
        'page_access_token' => env('META_PAGE_ACCESS_TOKEN'),
        'ig_user_id' => env('META_IG_USER_ID'),
        // Instagram and WhatsApp will use page token by default, but keep explicit env vars if needed
        'ig_access_token' => env('META_IG_ACCESS_TOKEN', env('META_PAGE_ACCESS_TOKEN')),
        'whatsapp_phone_number_id' => env('META_WHATSAPP_NUMBER_ID'),
        'whatsapp_token' => env('META_WHATSAPP_TOKEN', env('META_PAGE_ACCESS_TOKEN')),
        'api_version' => env('META_API_VERSION', 'v24.0'),
        'app_secret' => env('META_APP_SECRET'),
        'webhook_verify_token' => env('META_WEBHOOK_VERIFY_TOKEN'),
    ],

];
