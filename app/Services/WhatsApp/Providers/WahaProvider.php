<?php

declare(strict_types=1);

namespace App\Services\WhatsApp\Providers;

use App\Contracts\WhatsApp\WhatsAppProviderInterface;
use App\DTOs\WhatsApp\WhatsAppAnswerDto;
use App\DTOs\WhatsApp\WhatsAppTextMessageDto;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WahaProvider implements WhatsAppProviderInterface
{
    public function sendMessage(WhatsAppTextMessageDto $dto): WhatsAppAnswerDto
    {
        try {
            $response = Http::withHeaders($this->getHeaders())
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
        return null;
    }

    public function downloadMedia(string $mediaId, ?string $filename = null): ?string
    {
        try {
            $response = Http::withHeaders($this->getHeaders())
                ->get($this->getBaseUrl() . '/api/files/' . $mediaId);

            if (! $response->successful()) {
                return null;
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
                ->post($this->getBaseUrl() . '/api/sendSeen', [
                    'session' => $this->getSession(),
                    'chatId' => $this->extractChatId($messageId),
                ]);
        } catch (\Throwable $exception) {
            Log::channel('loki')->log($exception->getCode() === 1 ? 'warning' : 'error', $exception->getMessage(), ['file' => $exception->getFile(), 'line' => $exception->getLine()]);
        }
    }

    public function getMediaUrl(string $mediaId): ?string
    {
        return $this->getBaseUrl() . '/api/files/' . $mediaId;
    }

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
     * @return array<string, mixed>
     */
    private function buildPayload(WhatsAppTextMessageDto $dto): array
    {
        $basePayload = [
            'session' => $this->getSession(),
            'chatId' => $dto->to . '@c.us',
        ];

        return match ($dto->type) {
            'text' => $basePayload + ['text' => $dto->text],
            'image' => $basePayload + ['file' => $dto->mediaUrl ?? $dto->mediaId, 'caption' => $dto->caption],
            'document' => $basePayload + ['file' => $dto->mediaUrl ?? $dto->mediaId, 'caption' => $dto->caption, 'filename' => $dto->filename],
            'audio' => $basePayload + ['file' => $dto->mediaUrl ?? $dto->mediaId],
            'video' => $basePayload + ['file' => $dto->mediaUrl ?? $dto->mediaId, 'caption' => $dto->caption],
            'location' => $basePayload + ['latitude' => $dto->latitude, 'longitude' => $dto->longitude],
            default => $basePayload,
        };
    }

    /**
     * @return array<string, string>
     */
    private function getHeaders(): array
    {
        $headers = ['Content-Type' => 'application/json'];

        $apiKey = $this->getApiKey();

        if ($apiKey !== '') {
            $headers['X-Api-Key'] = $apiKey;
        }

        $basicAuth = $this->getBasicAuth();

        if ($basicAuth !== '') {
            $headers['Authorization'] = 'Basic ' . base64_encode($basicAuth);
        }

        return $headers;
    }

    private function getBasicAuth(): string
    {
        return (string) config('traffic_source.settings.whatsapp.waha.basic_auth', '');
    }

    private function getBaseUrl(): string
    {
        return rtrim((string) config('traffic_source.settings.whatsapp.waha.base_url', 'http://localhost:3000'), '/');
    }

    private function getApiKey(): string
    {
        return (string) config('traffic_source.settings.whatsapp.waha.api_key', '');
    }

    private function getSession(): string
    {
        return (string) config('traffic_source.settings.whatsapp.waha.session', 'default');
    }

    private function extractChatId(string $messageId): string
    {
        $parts = explode('_', $messageId);

        return $parts[1] ?? $messageId;
    }

    private function createTempPath(?string $filename, ?string $contentType): string
    {
        $tempDir = sys_get_temp_dir() . '/' . uniqid('waha_');
        mkdir($tempDir);

        if ($filename !== null && $filename !== '') {
            return $tempDir . '/' . $filename;
        }

        return $tempDir . '/' . uniqid('waha_media_', true) . '.' . $this->getExtensionFromContentType($contentType);
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
