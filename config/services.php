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
        'drive_invoice_folder_id'  => env('GOOGLE_DRIVE_INVOICE_FOLDER_ID', '1tuXzuzdhp96qD3xWlLb-XIa31nkZK5G-'),
        // DWD: the Workspace user the service account impersonates for Drive/Docs calls
        'impersonate_user'         => env('GOOGLE_IMPERSONATE_USER'),
        // Budget file generation
        'budget_template_spreadsheet_id' => env('GOOGLE_BUDGET_TEMPLATE_ID', '1a92I5kbmx4GCJ3g0vI9Q7Tp5RceVs0mRhd1f4YdoBZw'),
        'budget_spreadsheets_folder_id'  => env('GOOGLE_BUDGET_SPREADSHEETS_FOLDER_ID', '1iwUb06BwDEYSxuprcWQF9vyZ1xSrXzK7'),
        'budget_output_folder_id'        => env('GOOGLE_BUDGET_OUTPUT_FOLDER_ID', '1CtPZ4ib7ZeKjRJO9YIraWTWyI3mVvGqJ'),
        // Script Registration certificate generation
        'registration_template_id'       => env('GOOGLE_REGISTRATION_TEMPLATE_ID', '1qgeRv6I4HaMP6xozm_0bjyxuJ7kayXk3ZJsd5azNN7Q'),
        'registration_output_folder_id'  => env('GOOGLE_REGISTRATION_OUTPUT_FOLDER_ID', '15Ny6X-HNWMLWW5UUrlC8IFYfBjeM8Zv2'),
        'order_log_sheet_id'             => env('GOOGLE_ORDER_LOG_SHEET_ID', '1n3e9F-3dITPVDC_CNqg06aJDm1HdUhIY0b6WZ7JRmNE'),
        // The actual tab in that spreadsheet AppendOrderToSheet appends to — was hardcoded as
        // "Sheet1" (which doesn't exist in this spreadsheet at all; its tabs are "Orders and
        // Revenue", "SR Orders Log Backup", etc.), so every append failed with "Unable to parse
        // range" even once the domain-wide-delegation auth issue was fixed. Configurable since a
        // tab rename is far more likely than the file itself changing.
        'order_log_sheet_tab'            => env('GOOGLE_ORDER_LOG_SHEET_TAB', 'Order Log'),
    ],

    'portal' => [
        'webhook_secret' => env('PORTAL_WEBHOOK_SECRET'),
    ],

    'helpscout' => [
        'client_id'      => env('HELPSCOUT_CLIENT_ID'),
        'client_secret'  => env('HELPSCOUT_CLIENT_SECRET'),
        'webhook_secret' => env('HELPSCOUT_WEBHOOK_SECRET'),
    ],

    'woocommerce' => [
        'store_url'       => env('WC_STORE_URL'),
        'consumer_key'    => env('WC_CONSUMER_KEY'),
        'consumer_secret' => env('WC_CONSUMER_SECRET'),
        // Shared secret for the custom sr/v1 REST endpoints on the WP site
        'portal_secret'   => env('WC_PORTAL_SECRET'),
    ],

    'stripe' => [
        'secret_key'      => env('STRIPE_SECRET_KEY'),
        'publishable_key' => env('STRIPE_PUBLISHABLE_KEY'),
    ],

    'whereby' => [
        'api_key'        => env('WHEREBY_API_KEY'),
        'webhook_secret' => env('WHEREBY_WEBHOOK_SECRET'),
    ],

    'mailersend' => [
        'api_key'                   => env('MAILERSEND_API_KEY'),
        'assignment_template_id'    => env('MAILERSEND_ASSIGNMENT_TEMPLATE_ID', 'neqvygm95xdl0p7w'),
        'invoice_template_id'       => env('MAILERSEND_INVOICE_TEMPLATE_ID', 'vywj2lpzxepg7oqz'),
        'budget_template_id'        => env('MAILERSEND_BUDGET_TEMPLATE_ID', ''),
        'registration_template_id'  => env('MAILERSEND_REGISTRATION_TEMPLATE_ID', 'pq3enl6dozrg2vwr'),
        'qc_needs_attention_template_id' => env('MAILERSEND_QC_NEEDS_ATTENTION_TEMPLATE_ID', 'pxkjn4138jqgz781'),
        'video_consulting_cancelled_template_id'           => env('MAILERSEND_VIDEO_CONSULTING_CANCELLED_TEMPLATE_ID', ''),
        'video_consulting_reschedule_offer_template_id'    => env('MAILERSEND_VIDEO_CONSULTING_RESCHEDULE_OFFER_TEMPLATE_ID', ''),
        'video_consulting_reschedule_confirmed_template_id' => env('MAILERSEND_VIDEO_CONSULTING_RESCHEDULE_CONFIRMED_TEMPLATE_ID', ''),
        'video_consulting_admin_alert_template_id'         => env('MAILERSEND_VIDEO_CONSULTING_ADMIN_ALERT_TEMPLATE_ID', ''),
        'video_consulting_booking_confirmed_template_id'   => env('MAILERSEND_VIDEO_CONSULTING_BOOKING_CONFIRMED_TEMPLATE_ID', ''),
        'video_consulting_call_link_template_id'           => env('MAILERSEND_VIDEO_CONSULTING_CALL_LINK_TEMPLATE_ID', ''),
    ],

];
