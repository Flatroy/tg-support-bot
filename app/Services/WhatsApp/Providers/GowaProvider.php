<?php

declare(strict_types=1);

namespace App\Services\WhatsApp\Providers;

use App\Contracts\WhatsApp\WhatsAppProviderInterface;
use App\DTOs\WhatsApp\WhatsAppAnswerDto;
use App\DTOs\WhatsApp\WhatsAppTextMessageDto;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GowaProvider implements WhatsAppProviderInterface
{
    public function sendMessage(WhatsAppTextMessageDto $dto): WhatsAppAnswerDto
    {
        try {
            $response = Http::withHeaders($this->getHeaders())
                ->asMultipart()
                ->post($this->getBaseUrl() . $this->getSendEndpoint($dto->type), $this->buildPayload($dto));

            /** @var array<string, mixed> $data */
            $data = $response->json() ?? [];
            $data['response_code'] = $response->status();

            return WhatsAppAnswerDto::fromData($data);
        } catch (\Throwable $exception) {
            return WhatsAppAnswerDto::fromData([
                'response_code' => 500,
                'error' => ['message' => $exception->getMessage(), 'type' => 'internal'],
            ]);
        }
    }

    public function uploadMedia(string $filePath, string $mimeType): ?string
    {
        if (! file_exists($filePath)) {
            return null;
        }

        return $filePath;
    }

    public function downloadMedia(string $mediaUrl, ?string $filename = null): ?string
    {
        try {
            $url = $this->resolveMediaUrl($mediaUrl);

            $response = Http::withHeaders($this->getHeaders())->get($url);

            if (! $response->successful()) {
                Log::warning('GOWA download failed', ['url' => $url, 'status' => $response->status(), 'body' => substr($response->body(), 0, 200)]);

                return null;
            }

            // Message download API returns base64-encoded JSON
            if ($this->isMessageDownloadUrl($url)) {
                return $this->saveFromBase64Response($response->json() ?? [], $filename);
            }

            $filePath = $this->createTempPath($filename, $response->header('Content-Type'));
            file_put_contents($filePath, $response->body());

            return $filePath;
        } catch (\Throwable $exception) {
            Log::channel('loki')->log($exception->getCode() === 1 ? 'warning' : 'error', $exception->getMessage(), ['file' => $exception->getFile(), 'line' => $exception->getLine()]);

            return null;
        }
    }

    public function markAsRead(string $messageId): void
    {
        try {
            Http::withHeaders($this->getHeaders())
                ->post($this->getBaseUrl() . '/message/' . $messageId . '/read', [
                    'phone' => $this->extractPhoneFromMessageId($messageId),
                ]);
        } catch (\Throwable $exception) {
            Log::channel('loki')->log($exception->getCode() === 1 ? 'warning' : 'error', $exception->getMessage(), ['file' => $exception->getFile(), 'line' => $exception->getLine()]);
        }
    }

    public function getMediaUrl(string $mediaId): ?string
    {
        if (str_starts_with($mediaId, 'gowa_dl:')) {
            $parts = explode(':', $mediaId, 3);
            $msgId = $parts[1] ?? '';
            $fromJid = $parts[2] ?? '';

            return $this->getBaseUrl() . '/message/' . $msgId . '/download?phone=' . urlencode($fromJid);
        }

        return $this->resolveMediaUrl($mediaId);
    }

    private function isMessageDownloadUrl(string $url): bool
    {
        return str_contains($url, '/message/') && str_contains($url, '/download');
    }

    /**
     * @param array<string, mixed> $json
     */
    private function saveFromBase64Response(array $json, ?string $filename): ?string
    {
        $base64Data = isset($json['results']['data']) ? (string) $json['results']['data'] : null;

        if (empty($base64Data)) {
            Log::warning('GOWA download API: no data in response', ['keys' => array_keys($json)]);

            return null;
        }

        $binary = base64_decode($base64Data, strict: true);

        if ($binary === false) {
            Log::warning('GOWA download API: base64 decode failed');

            return null;
        }

        $mimeType = isset($json['results']['mime_type']) ? (string) $json['results']['mime_type'] : null;
        $apiFilename = isset($json['results']['file_name']) ? (string) $json['results']['file_name'] : null;
        $filePath = $this->createTempPath($apiFilename ?? $filename, $mimeType);
        file_put_contents($filePath, $binary);

        return $filePath;
    }

    private function resolveMediaUrl(string $mediaPath): string
    {
        if (filter_var($mediaPath, FILTER_VALIDATE_URL)) {
            return $mediaPath;
        }

        return rtrim($this->getBaseUrl(), '/') . '/' . ltrim($mediaPath, '/');
    }

    private function getSendEndpoint(string $type): string
    {
        return match ($type) {
            'image' => '/send/image',
            'document' => '/send/file',
            'audio' => '/send/audio',
            'video' => '/send/video',
            'location' => '/send/location',
            default => '/send/message',
        };
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function buildPayload(WhatsAppTextMessageDto $dto): array
    {
        $suffix = strlen($dto->to) > 15 ? '@g.us' : '@s.whatsapp.net';
        $phone = $dto->to . $suffix;

        $base = [
            ['name' => 'phone', 'contents' => $phone],
        ];

        return match ($dto->type) {
            'text' => array_merge($base, [
                ['name' => 'message', 'contents' => (string) $dto->text],
            ]),
            'image' => array_merge($base, $this->buildFileFields('image', $dto->mediaUrl ?? $dto->mediaId, $dto->caption)),
            'document' => array_merge($base, $this->buildFileFields('file', $dto->mediaUrl ?? $dto->mediaId, $dto->caption)),
            'audio' => array_merge($base, $this->buildFileFields('audio', $dto->mediaUrl ?? $dto->mediaId, null)),
            'video' => array_merge($base, $this->buildFileFields('video', $dto->mediaUrl ?? $dto->mediaId, $dto->caption)),
            'location' => array_merge($base, [
                ['name' => 'latitude', 'contents' => (string) $dto->latitude],
                ['name' => 'longitude', 'contents' => (string) $dto->longitude],
            ]),
            default => $base,
        };
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function buildFileFields(string $fieldName, ?string $filePath, ?string $caption): array
    {
        if ($filePath === null) {
            return [];
        }

        $fields = [];

        if (file_exists($filePath)) {
            $fields[] = [
                'name' => $fieldName,
                'contents' => fopen($filePath, 'rb'),
                'filename' => basename($filePath),
            ];
        } else {
            $fields[] = ['name' => $fieldName . '_url', 'contents' => $filePath];
        }

        if ($caption !== null && $caption !== '') {
            $fields[] = ['name' => 'caption', 'contents' => $caption];
        }

        return $fields;
    }

    /**
     * @return array<string, string>
     */
    private function getHeaders(): array
    {
        $headers = [];

        $basicAuth = $this->getBasicAuth();

        if ($basicAuth !== '') {
            $headers['Authorization'] = 'Basic ' . base64_encode($basicAuth);
        }

        $deviceId = $this->getDeviceId();

        if ($deviceId !== '') {
            $headers['X-Device-Id'] = $deviceId;
        }

        return $headers;
    }

    private function getBasicAuth(): string
    {
        return (string) config('traffic_source.settings.whatsapp.gowa.basic_auth', '');
    }

    private function getDeviceId(): string
    {
        return (string) config('traffic_source.settings.whatsapp.gowa.device_id', '');
    }

    private function getBaseUrl(): string
    {
        return rtrim((string) config('traffic_source.settings.whatsapp.gowa.base_url', 'http://localhost:3000'), '/');
    }

    private function extractPhoneFromMessageId(string $messageId): string
    {
        return $messageId;
    }

    private function createTempPath(?string $filename, ?string $contentType): string
    {
        $tempDir = sys_get_temp_dir() . '/' . uniqid('gowa_');
        mkdir($tempDir);

        if ($filename !== null && $filename !== '') {
            return $tempDir . '/' . $filename;
        }

        return $tempDir . '/' . uniqid('gowa_media_', true) . '.' . $this->getExtensionFromContentType($contentType);
    }

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
