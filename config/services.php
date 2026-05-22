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

    'google' => [
        // Path to service account JSON key file — set GOOGLE_APPLICATION_CREDENTIALS in .env
        'credentials_path'         => env('GOOGLE_APPLICATION_CREDENTIALS'),
        'drive_scripts_folder_id'  => env('GOOGLE_DRIVE_SCRIPTS_FOLDER_ID'),
        'drive_coverage_folder_id' => env('GOOGLE_DRIVE_COVERAGE_FOLDER_ID'),
        // DWD: the Workspace user the service account impersonates for Drive/Docs calls
        'impersonate_user'         => env('GOOGLE_IMPERSONATE_USER'),
    ],

    'portal' => [
        'webhook_secret' => env('PORTAL_WEBHOOK_SECRET'),
    ],

];
