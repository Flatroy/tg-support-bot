<?php

declare(strict_types=1);

namespace App\Services\WhatsApp\Providers;

use App\Contracts\WhatsApp\WhatsAppProviderInterface;
use App\DTOs\WhatsApp\WhatsAppAnswerDto;
use App\DTOs\WhatsApp\WhatsAppTextMessageDto;
use App\Logging\LokiLogger;
use Illuminate\Support\Facades\Http;

class WahaProvider implements WhatsAppProviderInterface
{
    /**
     * Send a message via WAHA.
     *
     * @param WhatsAppTextMessageDto $dto
     *
     * @return WhatsAppAnswerDto
     */
    public function sendMessage(WhatsAppTextMessageDto $dto): WhatsAppAnswerDto
    {
        try {
            $endpoint = $this->getSendEndpoint($dto->type);
            $payload = $this->buildPayload($dto);

            $response = Http::withHeaders($this->getHeaders())
                ->post($this->getBaseUrl() . $endpoint, $payload);

            $data = $response->json() ?? [];
            $data['response_code'] = $response->status();

            return WhatsAppAnswerDto::fromData($data);
        } catch (\Throwable $e) {
            return WhatsAppAnswerDto::fromData([
                'response_code' => 500,
                'error' => ['message' => $e->getMessage(), 'type' => 'internal'],
            ]);
        }
    }

    /**
     * WAHA handles media internally, no upload needed.
     *
     * @param string $filePath
     * @param string $mimeType
     *
     * @return null
     */
    public function uploadMedia(string $filePath, string $mimeType): ?string
    {
        return null;
    }

    /**
     * Download media from WAHA.
     *
     * @param string      $mediaId
     * @param string|null $filename
     *
     * @return string|null
     */
    public function downloadMedia(string $mediaId, ?string $filename = null): ?string
    {
        try {
            $response = Http::withHeaders($this->getHeaders())
                ->get($this->getBaseUrl() . '/api/files/' . $mediaId);

            if ($response->successful()) {
                if ($filename) {
                    $tempDir = sys_get_temp_dir() . '/' . uniqid('waha_');
                    mkdir($tempDir);
                    $tempPath = $tempDir . '/' . $filename;
                } else {
                    $contentType = $response->header('Content-Type');
                    $extension = $this->getExtensionFromContentType($contentType);
                    $tempPath = sys_get_temp_dir() . '/' . uniqid('waha_media_', true) . '.' . $extension;
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
     * Mark a message as read.
     *
     * @param string $messageId
     *
     * @return void
     */
    public function markAsRead(string $messageId): void
    {
        try {
            Http::withHeaders($this->getHeaders())
                ->post($this->getBaseUrl() . '/api/sendSeen', [
                    'session' => $this->getSession(),
                    'chatId' => $this->extractChatId($messageId),
                ]);
        } catch (\Throwable $e) {
            (new LokiLogger())->logException($e);
        }
    }

    /**
     * Get media URL for WAHA.
     *
     * @param string $mediaId
     *
     * @return string|null
     */
    public function getMediaUrl(string $mediaId): ?string
    {
        return $this->getBaseUrl() . '/api/files/' . $mediaId;
    }

    /**
     * Get the appropriate send endpoint based on message type.
     *
     * @param string $type
     *
     * @return string
     */
    private function getSendEndpoint(string $type): string
    {
        return match ($type) {
            'image' => '/api/sendImage',
            'document' => '/api/sendFile',
            'audio' => '/api/sendVoice',
            'video' => '/api/sendVideo',
            'location' => '/api/sendLocation',
            default => '/api/sendText',
        };
    }

    /**
     * Build WAHA payload from DTO.
     *
     * @param WhatsAppTextMessageDto $dto
     *
     * @return array
     */
    private function buildPayload(WhatsAppTextMessageDto $dto): array
    {
        $base = [
            'session' => $this->getSession(),
            'chatId' => $dto->to . '@c.us',
        ];

        return match ($dto->type) {
            'text' => array_merge($base, [
                'text' => $dto->text,
            ]),
            'image' => array_merge($base, [
                'file' => $dto->mediaUrl ?? $dto->mediaId,
                'caption' => $dto->caption,
            ]),
            'document' => array_merge($base, [
                'file' => $dto->mediaUrl ?? $dto->mediaId,
                'caption' => $dto->caption,
                'filename' => $dto->filename,
            ]),
            'audio' => array_merge($base, [
                'file' => $dto->mediaUrl ?? $dto->mediaId,
            ]),
            'video' => array_merge($base, [
                'file' => $dto->mediaUrl ?? $dto->mediaId,
                'caption' => $dto->caption,
            ]),
            'location' => array_merge($base, [
                'latitude' => $dto->latitude,
                'longitude' => $dto->longitude,
            ]),
            default => $base,
        };
    }

    /**
     * Get request headers for WAHA.
     *
     * @return array
     */
    private function getHeaders(): array
    {
        $headers = [
            'Content-Type' => 'application/json',
        ];

        $apiKey = $this->getApiKey();
        if ($apiKey) {
            $headers['X-Api-Key'] = $apiKey;
        }

        $basicAuth = $this->getBasicAuth();
        if ($basicAuth) {
            $headers['Authorization'] = 'Basic ' . base64_encode($basicAuth);
        }

        return $headers;
    }

    /**
     * Get WAHA Basic Auth credentials.
     *
     * @return string
     */
    private function getBasicAuth(): string
    {
        return config('traffic_source.settings.whatsapp.waha.basic_auth', '');
    }

    /**
     * Get WAHA base URL.
     *
     * @return string
     */
    private function getBaseUrl(): string
    {
        return rtrim(config('traffic_source.settings.whatsapp.waha.base_url', 'http://localhost:3000'), '/');
    }

    /**
     * Get WAHA API key.
     *
     * @return string
     */
    private function getApiKey(): string
    {
        return config('traffic_source.settings.whatsapp.waha.api_key', '');
    }

    /**
     * Get WAHA session name.
     *
     * @return string
     */
    private function getSession(): string
    {
        return config('traffic_source.settings.whatsapp.waha.session', 'default');
    }

    /**
     * Extract chat ID from WAHA message ID.
     *
     * @param string $messageId
     *
     * @return string
     */
    private function extractChatId(string $messageId): string
    {
        // WAHA message ID format: false_12345678901@c.us_ABCDEF
        $parts = explode('_', $messageId);
        if (count($parts) >= 2) {
            return $parts[1];
        }

        return $messageId;
    }

    /**
     * Get file extension from content type.
     *
     * @param string|null $contentType
     *
     * @return string
     */
    private function getExtensionFromContentType(?string $contentType): string
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
            'audio/wav' => 'wav',
            'audio/opus' => 'opus',
            'video/mp4' => 'mp4',
            'video/3gpp' => '3gp',
            'video/webm' => 'webm',
            'application/pdf' => 'pdf',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'xlsx',
            default => 'bin',
        };
    }
}
