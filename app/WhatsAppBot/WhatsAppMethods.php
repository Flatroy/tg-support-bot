<?php

declare(strict_types=1);

namespace App\WhatsAppBot;

use App\DTOs\WhatsApp\WhatsAppAnswerDto;
use App\DTOs\WhatsApp\WhatsAppTextMessageDto;
use App\Logging\LokiLogger;
use Illuminate\Support\Facades\Http;

class WhatsAppMethods
{
    /**
     * Send a message via WhatsApp Cloud API.
     *
     * @param WhatsAppTextMessageDto $messageDto
     *
     * @return WhatsAppAnswerDto
     */
    public static function sendMessage(WhatsAppTextMessageDto $messageDto): WhatsAppAnswerDto
    {
        try {
            $payload = $messageDto->toApiPayload();

            $response = Http::withToken(config('traffic_source.settings.whatsapp.token'))
                ->post(self::getBaseUrl() . '/messages', $payload);

            $resultQuery = $response->json() ?? [];
            $resultQuery['response_code'] = $response->status();

            return WhatsAppAnswerDto::fromData($resultQuery);
        } catch (\Throwable $e) {
            return WhatsAppAnswerDto::fromData([
                'response_code' => 500,
                'error' => ['message' => $e->getMessage(), 'type' => 'internal'],
            ]);
        }
    }

    /**
     * Upload media to WhatsApp Cloud API and return the media ID.
     *
     * @param string $filePath
     * @param string $mimeType
     *
     * @return string|null
     */
    public static function uploadMedia(string $filePath, string $mimeType): ?string
    {
        try {
            $version = config('traffic_source.settings.whatsapp.api_version', 'v21.0');
            $phoneNumberId = config('traffic_source.settings.whatsapp.phone_number_id');
            $url = "https://graph.facebook.com/{$version}/{$phoneNumberId}/media";

            $response = Http::withToken(config('traffic_source.settings.whatsapp.token'))
                ->attach('file', fopen($filePath, 'rb'), basename($filePath), ['Content-Type' => $mimeType])
                ->post($url, [
                    'messaging_product' => 'whatsapp',
                    'type' => $mimeType,
                ]);

            $data = $response->json();

            return $data['id'] ?? null;
        } catch (\Throwable $e) {
            (new LokiLogger())->logException($e);

            return null;
        }
    }

    /**
     * Mark a message as read.
     *
     * @param string $messageId
     *
     * @return void
     */
    public static function markAsRead(string $messageId): void
    {
        try {
            Http::withToken(config('traffic_source.settings.whatsapp.token'))
                ->post(self::getBaseUrl() . '/messages', [
                    'messaging_product' => 'whatsapp',
                    'status' => 'read',
                    'message_id' => $messageId,
                ]);
        } catch (\Throwable $e) {
            (new LokiLogger())->logException($e);
        }
    }

    /**
     * Get the download URL for a media file.
     *
     * @param string $mediaId
     *
     * @return string|null
     */
    public static function getMediaUrl(string $mediaId): ?string
    {
        try {
            $version = config('traffic_source.settings.whatsapp.api_version', 'v21.0');
            $response = Http::withToken(config('traffic_source.settings.whatsapp.token'))
                ->get("https://graph.facebook.com/{$version}/{$mediaId}");

            return $response->json('url');
        } catch (\Throwable $e) {
            (new LokiLogger())->logException($e);

            return null;
        }
    }

    /**
     * Download media content from WhatsApp.
     *
     * @param string      $mediaUrl
     * @param string|null $originalFilename
     *
     * @return string|null
     */
    public static function downloadMedia(string $mediaUrl, ?string $originalFilename = null): ?string
    {
        try {
            $response = Http::withToken(config('traffic_source.settings.whatsapp.token'))
                ->get($mediaUrl);

            if ($response->successful()) {
                if ($originalFilename) {
                    $tempDir = sys_get_temp_dir() . '/' . uniqid('wa_');
                    mkdir($tempDir);
                    $tempPath = $tempDir . '/' . $originalFilename;
                } else {
                    $contentType = $response->header('Content-Type');
                    $extension = self::getExtensionFromContentType($contentType);
                    $tempPath = sys_get_temp_dir() . '/' . uniqid('wa_media_', true) . '.' . $extension;
                }
                file_put_contents($tempPath, $response->body());

                return $tempPath;
            }

            return null;
        } catch (\Throwable $e) {
            (new LokiLogger())->logException($e);

            return null;
        }
    }

    /**
     * @param string|null $contentType
     *
     * @return string
     */
    private static function getExtensionFromContentType(?string $contentType): string
    {
        $mime = strtok($contentType ?? '', ';');

        return match (trim($mime)) {
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            'image/gif' => 'gif',
            'audio/ogg' => 'ogg',
            'audio/mpeg' => 'mp3',
            'audio/aac' => 'aac',
            'video/mp4' => 'mp4',
            'video/3gpp' => '3gp',
            'application/pdf' => 'pdf',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'xlsx',
            default => 'bin',
        };
    }

    /**
     * @return string
     */
    private static function getBaseUrl(): string
    {
        $version = config('traffic_source.settings.whatsapp.api_version', 'v21.0');
        $phoneNumberId = config('traffic_source.settings.whatsapp.phone_number_id');

        return "https://graph.facebook.com/{$version}/{$phoneNumberId}";
    }
}
