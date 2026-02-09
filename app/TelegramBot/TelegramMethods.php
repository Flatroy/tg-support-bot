<?php

declare(strict_types=1);

namespace App\TelegramBot;

use App\DTOs\TelegramAnswerDto;

class TelegramMethods
{
    /**
     * Send request to Telegram with rate limit check.
     *
     * @param string $methodQuery
     * @param ?array $dataQuery
     *
     * @return TelegramAnswerDto
     */
    public static function sendQueryTelegram(string $methodQuery, ?array $dataQuery = null, ?string $token = null): TelegramAnswerDto
    {
        try {
            $token = $token ?? config('traffic_source.settings.telegram.token');

            $domainQuery = 'https://api.telegram.org/bot' . $token . '/';
            $urlQuery = $domainQuery . $methodQuery;

            if (!empty($dataQuery['uploaded_file_path']) && empty($dataQuery['uploaded_file'])) {
                $path = $dataQuery['uploaded_file_path'];
                unset($dataQuery['uploaded_file_path']);
                $dataQuery['uploaded_file'] = new \Illuminate\Http\UploadedFile(
                    $path,
                    basename($path),
                    mime_content_type($path) ?: null,
                    null,
                    true
                );
            }

            if (!empty($dataQuery['uploaded_file'])) {
                $attachType = match ($methodQuery) {
                    'sendPhoto' => 'photo',
                    'sendVoice' => 'voice',
                    'sendSticker' => 'sticker',
                    'sendVideoNote' => 'video_note',
                    'sendAudio' => 'audio',
                    'sendVideo' => 'video',
                    default => 'document',
                };
                $resultQuery = ParserMethods::attachQuery($urlQuery, $dataQuery, $attachType);
            } else {
                $resultQuery = ParserMethods::postQuery($urlQuery, $dataQuery);
            }

            return TelegramAnswerDto::fromData($resultQuery);
        } catch (\Throwable $e) {
            return TelegramAnswerDto::fromData([
                'ok' => false,
                'response_code' => 500,
                'result' => $e->getMessage(),
            ]);
        }
    }
}
