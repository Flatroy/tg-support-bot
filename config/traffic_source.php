<?php

return [
    'settings' => [
        'telegram' => [
            'token' => env('TELEGRAM_TOKEN', ''),
            'secret_key' => env('TELEGRAM_SECRET_KEY', ''),
            'group_id' => env('TELEGRAM_GROUP_ID', ''),
            // use IPv4 only to connect to Telegram api
            'force_ipv4' => (bool)env('TELEGRAM_FORCE_IPV4', false),
            'template_topic_name' => env('TELEGRAM_TOPIC_NAME', ''),
        ],
        'telegram_ai' => [
            'username' => env('TELEGRAM_AI_BOT_USERNAME', ''),
            'token' => env('TELEGRAM_AI_BOT_TOKEN', ''),
        ],

        'vk' => [
            'token' => env('VK_TOKEN', ''),
            'secret_key' => env('VK_SECRET_CODE', ''),
            'confirm_code' => env('VK_CONFIRM_CODE', ''),
        ],

        'whatsapp' => [
            'provider' => env('WHATSAPP_PROVIDER', 'cloud'), // 'cloud' or 'waha'
            // Cloud API settings (existing)
            'token' => env('WHATSAPP_TOKEN', ''),
            'phone_number_id' => env('WHATSAPP_PHONE_NUMBER_ID', ''),
            'verify_token' => env('WHATSAPP_VERIFY_TOKEN', ''),
            'app_secret' => env('WHATSAPP_APP_SECRET', ''),
            'api_version' => env('WHATSAPP_API_VERSION', 'v21.0'),
            // WAHA settings (new)
            'waha' => [
                'base_url' => env('WAHA_BASE_URL', 'http://localhost:3000'),
                'api_key' => env('WAHA_API_KEY', ''),
                'session' => env('WAHA_SESSION', 'default'),
                'basic_auth' => env('WAHA_BASIC_AUTH', ''), // e.g., 'admin:password'
            ],
        ],
    ],
];
