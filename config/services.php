<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Resend, Postmark, AWS, and more. This file provides the de facto
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

    /*
     * Webpay Plus by Transbank, REST API v1.2. The defaults are the public
     * integration (sandbox) credentials that Transbank publishes in its
     * docs and SDKs: they move no real money. Production would need each
     * studio's own commerce code, which is out of this project's scope.
     */
    'webpay' => [
        'base_url' => env('WEBPAY_BASE_URL', 'https://webpay3gint.transbank.cl'),
        'commerce_code' => env('WEBPAY_COMMERCE_CODE', '597055555532'),
        'api_key' => env('WEBPAY_API_KEY', '579B532A7440BB0C9079DED94D31EA1615BACEB56610332264630D42D0A36B1C'),
    ],

];
