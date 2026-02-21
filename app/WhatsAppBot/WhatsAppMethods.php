<?php

declare(strict_types=1);

namespace App\WhatsAppBot;

use App\DTOs\WhatsApp\WhatsAppAnswerDto;
use App\DTOs\WhatsApp\WhatsAppTextMessageDto;
use App\Services\WhatsApp\WhatsAppProviderFactory;

class WhatsAppMethods
{
    /**
     * Send a message via WhatsApp using the configured provider.
     *
     * @param WhatsAppTextMessageDto $messageDto
     *
     * @return WhatsAppAnswerDto
     */
    public static function sendMessage(WhatsAppTextMessageDto $messageDto): WhatsAppAnswerDto
    {
        return WhatsAppProviderFactory::make()->sendMessage($messageDto);
    }

    /**
     * Upload media to WhatsApp using the configured provider.
     *
     * @param string $filePath
     * @param string $mimeType
     *
     * @return string|null
     */
    public static function uploadMedia(string $filePath, string $mimeType): ?string
    {
        return WhatsAppProviderFactory::make()->uploadMedia($filePath, $mimeType);
    }

    /**
     * Mark a message as read using the configured provider.
     *
     * @param string $messageId
     *
     * @return void
     */
    public static function markAsRead(string $messageId): void
    {
        WhatsAppProviderFactory::make()->markAsRead($messageId);
    }

    /**
     * Get the download URL for a media file using the configured provider.
     *
     * @param string $mediaId
     *
     * @return string|null
     */
    public static function getMediaUrl(string $mediaId): ?string
    {
        return WhatsAppProviderFactory::make()->getMediaUrl($mediaId);
    }

    /**
     * Download media content from WhatsApp using the configured provider.
     *
     * @param string      $mediaUrl
     * @param string|null $originalFilename
     *
     * @return string|null
     */
    public static function downloadMedia(string $mediaUrl, ?string $originalFilename = null): ?string
    {
        return WhatsAppProviderFactory::make()->downloadMedia($mediaUrl, $originalFilename);
    }
}
