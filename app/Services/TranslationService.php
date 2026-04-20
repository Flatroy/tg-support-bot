<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

final class TranslationService
{
    /**
     * Returns the original text with the translation appended, or just the
     * original text if translation is disabled, unnecessary, or fails.
     */
    public function maybeTranslate(string $text): string
    {
        if (! $this->shouldTranslate($text)) {
            return $text;
        }

        $translated = $this->callDeepL($text);
        if ($translated === null) {
            return $text;
        }

        $label = (string) config('translation.append_label', '🌐 Перевод:');

        return $text . "\n\n" . $label . ' ' . $translated;
    }

    public function shouldTranslate(string $text): bool
    {
        if (! config('translation.enabled', false)) {
            return false;
        }

        if (empty(config('translation.deepl_api_key'))) {
            return false;
        }

        $sourceLang = strtoupper((string) config('translation.source_language', 'HE'));

        return $this->textContainsLanguage($text, $sourceLang);
    }

    private function textContainsLanguage(string $text, string $langCode): bool
    {
        return match ($langCode) {
            'HE' => $this->containsHebrew($text),
            'AR' => $this->containsArabic($text),
            default => false,
        };
    }

    private function containsHebrew(string $text): bool
    {
        return (bool) preg_match('/[\x{0590}-\x{05FF}\x{FB1D}-\x{FB4F}]/u', $text);
    }

    private function containsArabic(string $text): bool
    {
        return (bool) preg_match('/[\x{0600}-\x{06FF}\x{0750}-\x{077F}\x{FB50}-\x{FDFF}\x{FE70}-\x{FEFF}]/u', $text);
    }

    private function callDeepL(string $text): ?string
    {
        try {
            $apiKey = (string) config('translation.deepl_api_key');
            $apiUrl = rtrim((string) config('translation.deepl_api_url', 'https://api-free.deepl.com/v2'), '/');
            $sourceLang = strtoupper((string) config('translation.source_language', 'HE'));
            $targetLang = strtoupper((string) config('translation.target_language', 'RU'));

            $response = Http::withHeaders([
                'Authorization' => 'DeepL-Auth-Key ' . $apiKey,
            ])->post($apiUrl . '/translate', [
                'text' => [$text],
                'source_lang' => $sourceLang,
                'target_lang' => $targetLang,
            ]);

            if (! $response->successful()) {
                Log::warning('DeepL translation failed', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                return null;
            }

            $result = $response->json('translations.0.text');

            return is_string($result) ? $result : null;
        } catch (\Throwable $e) {
            Log::warning('DeepL translation exception: ' . $e->getMessage());

            return null;
        }
    }
}
