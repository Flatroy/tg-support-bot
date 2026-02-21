<?php

declare(strict_types=1);

namespace App\Services\WhatsApp\Providers;

use App\Contracts\WhatsApp\WhatsAppProviderInterface;
use App\DTOs\WhatsApp\WhatsAppAnswerDto;
use App\DTOs\WhatsApp\WhatsAppTextMessageDto;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class CloudApiProvider implements WhatsAppProviderInterface
{
    public function sendMessage(WhatsAppTextMessageDto $dto): WhatsAppAnswerDto
    {
        try {
            $response = Http::withToken($this->token())
                ->post($this->getBaseUrl() . '/messages', $dto->toApiPayload());

            /** @var array<string, mixed> $data */
            $data = $response->json() ?? [];
            $data['response_code'] = $response->status();

            return WhatsAppAnswerDto::fromData($data);
        } catch (\Throwable $exception) {
            return $this->errorResponse($exception);
        }
    }

    public function uploadMedia(string $filePath, string $mimeType): ?string
    {
        try {
            $response = Http::withToken($this->token())
                ->attach('file', fopen($filePath, 'rb'), basename($filePath), ['Content-Type' => $mimeType])
                ->post($this->getBaseUrl() . '/media', [
                    'messaging_product' => 'whatsapp',
                    'type' => $mimeType,
                ]);

            return $response->json('id');
        } catch (\Throwable $exception) {
            $this->logException($exception);

            return null;
        }
    }

    public function markAsRead(string $messageId): void
    {
        try {
            Http::withToken($this->token())
                ->post($this->getBaseUrl() . '/messages', [
                    'messaging_product' => 'whatsapp',
                    'status' => 'read',
                    'message_id' => $messageId,
                ]);
        } catch (\Throwable $exception) {
            $this->logException($exception);
        }
    }

    public function getMediaUrl(string $mediaId): ?string
    {
        try {
            return Http::withToken($this->token())
                ->get('https://graph.facebook.com/' . $this->version() . '/' . $mediaId)
                ->json('url');
        } catch (\Throwable $exception) {
            $this->logException($exception);

            return null;
        }
    }

    public function downloadMedia(string $mediaUrl, ?string $filename = null): ?string
    {
        try {
            $response = Http::withToken($this->token())->get($mediaUrl);

            if (! $response->successful()) {
                return null;
            }

            $filePath = $this->createTempPath($filename, $response->header('Content-Type'));
            file_put_contents($filePath, $response->body());

            return $filePath;
        } catch (\Throwable $exception) {
            $this->logException($exception);

            return null;
        }
    }

    private function getBaseUrl(): string
    {
        return 'https://graph.facebook.com/' . $this->version() . '/' . config('traffic_source.settings.whatsapp.phone_number_id');
    }

    private function version(): string
    {
        return (string) config('traffic_source.settings.whatsapp.api_version', 'v21.0');
    }

    private function token(): string
    {
        return (string) config('traffic_source.settings.whatsapp.token');
    }

    private function logException(\Throwable $exception): void
    {
        Log::channel('loki')->log($exception->getCode() === 1 ? 'warning' : 'error', $exception->getMessage(), ['file' => $exception->getFile(), 'line' => $exception->getLine()]);
    }

    private function errorResponse(\Throwable $exception): WhatsAppAnswerDto
    {
        return WhatsAppAnswerDto::fromData([
            'response_code' => 500,
            'error' => ['message' => $exception->getMessage(), 'type' => 'internal'],
        ]);
    }

    private function createTempPath(?string $filename, ?string $contentType): string
    {
        $tempDir = sys_get_temp_dir() . '/' . uniqid('wa_');
        mkdir($tempDir);

        if ($filename !== null && $filename !== '') {
            return $tempDir . '/' . $filename;
        }

        return $tempDir . '/' . uniqid('wa_media_', true) . '.' . $this->getExtensionFromContentType($contentType);
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
            'video/mp4' => 'mp4',
            'video/3gpp' => '3gp',
            'application/pdf' => 'pdf',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'xlsx',
            default => 'bin',
        };
    }
}
