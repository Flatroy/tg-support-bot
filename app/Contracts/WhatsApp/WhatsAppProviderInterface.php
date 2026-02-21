<?php

declare(strict_types=1);

namespace App\Contracts\WhatsApp;

use App\DTOs\WhatsApp\WhatsAppAnswerDto;
use App\DTOs\WhatsApp\WhatsAppTextMessageDto;

interface WhatsAppProviderInterface
{
    /**
     * Send a message via WhatsApp.
     *
     * @param WhatsAppTextMessageDto $dto
     *
     * @return WhatsAppAnswerDto
     */
    public function sendMessage(WhatsAppTextMessageDto $dto): WhatsAppAnswerDto;

    /**
     * Upload media to WhatsApp and return the media ID.
     *
     * @param string $filePath
     * @param string $mimeType
     *
     * @return string|null
     */
    public function uploadMedia(string $filePath, string $mimeType): ?string;

    /**
     * Download media from WhatsApp.
     *
     * @param string      $mediaId
     * @param string|null $filename
     *
     * @return string|null
     */
    public function downloadMedia(string $mediaId, ?string $filename = null): ?string;

    /**
     * Mark a message as read.
     *
     * @param string $messageId
     *
     * @return void
     */
    public function markAsRead(string $messageId): void;

    /**
     * Get the media URL for a media ID.
     *
     * @param string $mediaId
     *
     * @return string|null
     */
    public function getMediaUrl(string $mediaId): ?string;
}
