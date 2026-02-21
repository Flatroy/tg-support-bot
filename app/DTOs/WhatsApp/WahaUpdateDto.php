<?php

declare(strict_types=1);

namespace App\DTOs\WhatsApp;

use Illuminate\Http\Request;

readonly class WahaUpdateDto
{
    /**
     * @param array<string, mixed>|null                              $location
     * @param array<int, string>|null                                $contacts
     * @param array<string, mixed>|null                              $reaction
     * @param array{ack: int|string|null, ackName: string|null}|null $status
     * @param array<string, mixed>                                   $rawData
     */
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

    public static function fromRequest(Request $request): ?self
    {
        try {
            /** @var array<string, mixed> $data */
            $data = $request->all();

            if (($data['event'] ?? null) === 'message') {
                return self::fromMessageEvent(self::arrayValue($data, 'payload'), $data);
            }

            if (($data['event'] ?? null) === 'message.ack') {
                return self::fromAckEvent(self::arrayValue($data, 'payload'), $data);
            }

            if (isset($data['id'], $data['from'], $data['body'])) {
                return self::fromRawMessage($data);
            }

            return null;
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @param array<string, mixed> $payload
     * @param array<string, mixed> $rawData
     */
    private static function fromMessageEvent(array $payload, array $rawData): self
    {
        return self::fromPayload($payload, $rawData);
    }

    /**
     * @param array<string, mixed> $payload
     * @param array<string, mixed> $rawData
     */
    private static function fromAckEvent(array $payload, array $rawData): self
    {
        return new self(
            messageId: (string) ($payload['id'] ?? ''),
            from: (string) ($payload['to'] ?? ''),
            chatId: (string) ($payload['to'] ?? ''),
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
                'ackName' => isset($payload['ackName']) ? (string) $payload['ackName'] : null,
            ],
            rawData: $rawData,
        );
    }

    /**
     * @param array<string, mixed> $data
     */
    private static function fromRawMessage(array $data): self
    {
        return self::fromPayload($data, $data);
    }

    /**
     * @param array<string, mixed> $payload
     * @param array<string, mixed> $rawData
     */
    private static function fromPayload(array $payload, array $rawData): self
    {
        $mediaData = self::extractMediaData($payload);

        return new self(
            messageId: (string) ($payload['id'] ?? ''),
            from: (string) ($payload['from'] ?? ''),
            chatId: (string) ($payload['from'] ?? ''),
            type: self::determineType($payload),
            text: isset($payload['body']) ? (string) $payload['body'] : null,
            mediaId: $mediaData['id'],
            mimeType: $mediaData['mimeType'],
            filename: $mediaData['filename'],
            caption: $mediaData['caption'],
            location: self::nullableArrayValue($payload, 'location'),
            contacts: self::nullableStringListValue($payload, 'vCards'),
            reaction: self::nullableArrayValue($payload, 'reaction'),
            status: null,
            rawData: $rawData,
        );
    }

    /**
     * @param array<string, mixed> $payload
     */
    private static function determineType(array $payload): string
    {
        if (! empty($payload['location'])) {
            return 'location';
        }

        if (! empty($payload['vCards'])) {
            return 'contacts';
        }

        if (! empty($payload['hasMedia'])) {
            $media = self::arrayValue($payload, 'media');
            $mime = (string) ($media['mimetype'] ?? '');

            return match (true) {
                str_starts_with($mime, 'image/') => 'image',
                str_starts_with($mime, 'video/') => 'video',
                str_starts_with($mime, 'audio/') => 'audio',
                default => 'document',
            };
        }

        if (! empty($payload['reaction'])) {
            return 'reaction';
        }

        return 'text';
    }

    /**
     * @param array<string, mixed> $payload
     *
     * @return array{id: ?string, mimeType: ?string, filename: ?string, caption: ?string}
     */
    private static function extractMediaData(array $payload): array
    {
        if (empty($payload['hasMedia'])) {
            return ['id' => null, 'mimeType' => null, 'filename' => null, 'caption' => null];
        }

        $media = self::arrayValue($payload, 'media');

        return [
            'id' => isset($media['id']) ? (string) $media['id'] : null,
            'mimeType' => isset($media['mimetype']) ? (string) $media['mimetype'] : null,
            'filename' => isset($media['filename']) ? (string) $media['filename'] : null,
            'caption' => isset($payload['body']) ? (string) $payload['body'] : null,
        ];
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return array<string, mixed>
     */
    private static function arrayValue(array $data, string $key): array
    {
        $value = $data[$key] ?? null;

        return is_array($value) ? $value : [];
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return array<string, mixed>|null
     */
    private static function nullableArrayValue(array $data, string $key): ?array
    {
        $value = $data[$key] ?? null;

        return is_array($value) ? $value : null;
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return array<int, string>|null
     */
    private static function nullableStringListValue(array $data, string $key): ?array
    {
        $value = $data[$key] ?? null;

        if (! is_array($value)) {
            return null;
        }

        $items = array_values(array_filter($value, static fn (mixed $item): bool => is_string($item)));

        return $items === [] ? null : $items;
    }
}
