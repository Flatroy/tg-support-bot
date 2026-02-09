<?php

declare(strict_types=1);

namespace App\DTOs\WhatsApp;

use Spatie\LaravelData\Data;

class WhatsAppTextMessageDto extends Data
{
    public function __construct(
        public string $to,
        public string $type,
        public ?string $text = null,
        public ?string $mediaUrl = null,
        public ?string $mediaId = null,
        public ?string $mimeType = null,
        public ?string $caption = null,
        public ?string $filename = null,
        public ?float $latitude = null,
        public ?float $longitude = null,
        public ?string $templateName = null,
        public ?string $templateLanguage = null,
        public ?array $templateComponents = null,
    ) {
    }

    /**
     * Build the WhatsApp Cloud API payload.
     *
     * @return array
     */
    public function toApiPayload(): array
    {
        $payload = [
            'messaging_product' => 'whatsapp',
            'recipient_type' => 'individual',
            'to' => $this->to,
            'type' => $this->type,
        ];

        return match ($this->type) {
            'text' => array_merge($payload, [
                'text' => ['body' => $this->text],
            ]),
            'image' => array_merge($payload, [
                'image' => array_filter([
                    'link' => $this->mediaUrl,
                    'id' => $this->mediaId,
                    'caption' => $this->caption,
                ]),
            ]),
            'document' => array_merge($payload, [
                'document' => array_filter([
                    'link' => $this->mediaUrl,
                    'id' => $this->mediaId,
                    'caption' => $this->caption,
                    'filename' => $this->filename,
                ]),
            ]),
            'audio' => array_merge($payload, [
                'audio' => array_filter([
                    'link' => $this->mediaUrl,
                    'id' => $this->mediaId,
                ]),
            ]),
            'video' => array_merge($payload, [
                'video' => array_filter([
                    'link' => $this->mediaUrl,
                    'id' => $this->mediaId,
                    'caption' => $this->caption,
                ]),
            ]),
            'location' => array_merge($payload, [
                'location' => [
                    'latitude' => $this->latitude,
                    'longitude' => $this->longitude,
                ],
            ]),
            'template' => array_merge($payload, [
                'template' => array_filter([
                    'name' => $this->templateName,
                    'language' => ['code' => $this->templateLanguage ?? 'en'],
                    'components' => $this->templateComponents,
                ]),
            ]),
            default => $payload,
        };
    }
}
