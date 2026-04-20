<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Translation Configuration
    |--------------------------------------------------------------------------
    |
    | When enabled, incoming WhatsApp messages containing text in the
    | configured source language will be translated via DeepL and the
    | translation will be appended to the original message.
    |
    */

    'enabled' => env('TRANSLATION_ENABLED', false),

    /*
    |--------------------------------------------------------------------------
    | DeepL API
    |--------------------------------------------------------------------------
    |
    | Use the free-tier endpoint (api-free.deepl.com) for free API keys,
    | or api.deepl.com for Pro keys.
    |
    */

    'deepl_api_key' => env('DEEPL_API_KEY', ''),

    'deepl_api_url' => env('DEEPL_API_URL', 'https://api-free.deepl.com/v2'),

    /*
    |--------------------------------------------------------------------------
    | Languages
    |--------------------------------------------------------------------------
    |
    | source_language: BCP-47 language code to detect and translate FROM.
    |   Currently supports automatic detection for: HE (Hebrew), AR (Arabic).
    |   Set to null to always translate regardless of detected language.
    |
    | target_language: DeepL language code to translate TO (e.g. RU, EN-US).
    |
    */

    'source_language' => env('TRANSLATION_SOURCE_LANG', 'HE'),

    'target_language' => env('TRANSLATION_TARGET_LANG', 'RU'),

    /*
    |--------------------------------------------------------------------------
    | Append Label
    |--------------------------------------------------------------------------
    |
    | Label prepended to the translated block appended after the original text.
    |
    */

    'append_label' => env('TRANSLATION_APPEND_LABEL', '🌐 Перевод (HE→RU):'),
];
