<?php

declare(strict_types=1);

namespace App\DTOs\WhatsApp;

use Illuminate\Http\Request;

/**
 * DTO for WAHA (WhatsApp HTTP API) webhook updates.
 */
readonly class WahaUpdateDto
{
    public function __construct(
        public string $messageId,
        public string $from,
        public string $chatId,
        public string $type,
        public ?string $text,
        public ?string $mediaId,
        public ?string $mimeType,
        public ?string $filename,
        public ?string $caption,
        public ?array $location,
        public ?array $contacts,
        public ?array $reaction,
        public ?array $status,
        public array $rawData,
    ) {
    }

    /**
     * @param Request $request
     *
     * @return self|null
     */
    public static function fromRequest(Request $request): ?self
    {
        try {
            $data = $request->all();

            // Check if it's a WAHA message event
            if (isset($data['event']) && $data['event'] === 'message') {
                return self::fromMessageEvent($data['payload'] ?? [], $data);
            }

            // Check if it's a WAHA status event
            if (isset($data['event']) && $data['event'] === 'message.ack') {
                return self::fromAckEvent($data['payload'] ?? [], $data);
            }

            // Check if it's a raw WAHA message format (direct payload)
            if (isset($data['id']) && isset($data['from']) && isset($data['body'])) {
                return self::fromRawMessage($data);
            }

            return null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * @param array $payload
     * @param array $rawData
     *
     * @return self
     */
    private static function fromMessageEvent(array $payload, array $rawData): self
    {
        $type = self::determineType($payload);
        $mediaData = self::extractMediaData($payload);

        return new self(
            messageId: $payload['id'] ?? '',
            from: $payload['from'] ?? '',
            chatId: $payload['from'] ?? '',
            type: $type,
            text: $payload['body'] ?? null,
            mediaId: $mediaData['id'],
            mimeType: $mediaData['mimeType'],
            filename: $mediaData['filename'],
            caption: $mediaData['caption'],
            location: $payload['location'] ?? null,
            contacts: $payload['vCards'] ?? null,
            reaction: $payload['reaction'] ?? null,
            status: null,
            rawData: $rawData,
        );
    }

    /**
     * @param array $payload
     * @param array $rawData
     *
     * @return self
     */
    private static function fromAckEvent(array $payload, array $rawData): self
    {
        return new self(
            messageId: $payload['id'] ?? '',
            from: $payload['to'] ?? '',
            chatId: $payload['to'] ?? '',
            type: 'status',
            text: null,
            mediaId: null,
            mimeType: null,
            filename: null,
            caption: null,
            location: null,
            contacts: null,
            reaction: null,
            status: [
                'ack' => $payload['ack'] ?? null,
                'ackName' => $payload['ackName'] ?? null,
            ],
            rawData: $rawData,
        );
    }

    /**
     * @param array $data
     *
     * @return self
     */
    private static function fromRawMessage(array $data): self
    {
        $type = self::determineType($data);
        $mediaData = self::extractMediaData($data);

        return new self(
            messageId: $data['id'] ?? '',
            from: $data['from'] ?? '',
            chatId: $data['from'] ?? '',
            type: $type,
            text: $data['body'] ?? null,
            mediaId: $mediaData['id'],
            mimeType: $mediaData['mimeType'],
            filename: $mediaData['filename'],
            caption: $mediaData['caption'],
            location: $data['location'] ?? null,
            contacts: $data['vCards'] ?? null,
            reaction: $data['reaction'] ?? null,
            status: null,
            rawData: $data,
        );
    }

    /**
     * @param array $payload
     *
     * @return string
     */
    private static function determineType(array $payload): string
    {
        if (!empty($payload['location'])) {
            return 'location';
        }

        if (!empty($payload['vCards'])) {
            return 'contacts';
        }

        if (!empty($payload['hasMedia'])) {
            $mime = $payload['media']['mimetype'] ?? '';

            return match (true) {
                str_starts_with($mime, 'image/') => 'image',
                str_starts_with($mime, 'video/') => 'video',
                str_starts_with($mime, 'audio/') => 'audio',
                default => 'document',
            };
        }

        if (!empty($payload['reaction'])) {
            return 'reaction';
        }

        return 'text';
    }

    /**
     * @param array $payload
     *
     * @return array
     */
    private static function extractMediaData(array $payload): array
    {
        if (empty($payload['hasMedia']) || empty($payload['media'])) {
            return ['id' => null, 'mimeType' => null, 'filename' => null, 'caption' => null];
        }

        $media = $payload['media'];

        return [
            'id' => $media['id'] ?? null,
            'mimeType' => $media['mimetype'] ?? null,
            'filename' => $media['filename'] ?? null,
            'caption' => $payload['body'] ?? null,
        ];
    }
}
