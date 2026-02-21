<?php

declare(strict_types=1);

namespace App\Contracts\WhatsApp;

use App\DTOs\WhatsApp\WhatsAppAnswerDto;
use App\DTOs\WhatsApp\WhatsAppTextMessageDto;

interface WhatsAppProviderInterface
{
    public function sendMessage(WhatsAppTextMessageDto $dto): WhatsAppAnswerDto;

    public function uploadMedia(string $filePath, string $mimeType): ?string;

    public function downloadMedia(string $mediaId, ?string $filename = null): ?string;

    public function markAsRead(string $messageId): void;

    public function getMediaUrl(string $mediaId): ?string;
}
