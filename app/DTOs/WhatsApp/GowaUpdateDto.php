<?php

declare(strict_types=1);

namespace App\DTOs\WhatsApp;

use Illuminate\Http\Request;

readonly class GowaUpdateDto
{
    /**
     * @param array<string, mixed>|null $location
     * @param array<int, string>|null   $contacts
     * @param array<string, mixed>|null $reaction
     * @param array<string, mixed>      $rawData
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
        public array $rawData,
        public ?string $senderName = null,
    ) {
    }

    public static function fromRequest(Request $request): ?self
    {
        try {
            /** @var array<string, mixed> $data */
            $data = $request->all();

            $event = (string) ($data['event'] ?? '');

            if ($event === 'message') {
                $payload = self::arrayValue($data, 'payload');

                if (empty($payload)) {
                    return null;
                }

                if (! empty($payload['is_from_me'])) {
                    return null;
                }

                return self::fromPayload($payload, $data);
            }

            if ($event === 'message.reaction') {
                $payload = self::arrayValue($data, 'payload');

                if (empty($payload)) {
                    return null;
                }

                return self::fromReactionPayload($payload, $data);
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
    private static function fromPayload(array $payload, array $rawData): self
    {
        $mediaData = self::extractMediaData($payload);

        return new self(
            messageId: (string) ($payload['id'] ?? ''),
            from: (string) ($payload['from'] ?? $payload['chat_id'] ?? ''),
            chatId: (string) ($payload['chat_id'] ?? $payload['from'] ?? ''),
            type: self::determineType($payload),
            text: isset($payload['body']) ? (string) $payload['body'] : null,
            mediaId: $mediaData['id'],
            mimeType: $mediaData['mimeType'],
            filename: $mediaData['filename'],
            caption: $mediaData['caption'],
            location: self::nullableArrayValue($payload, 'location'),
            contacts: self::extractContacts($payload),
            reaction: null,
            rawData: $rawData,
            senderName: isset($payload['from_name']) ? (string) $payload['from_name'] : null,
        );
    }

    /**
     * @param array<string, mixed> $payload
     * @param array<string, mixed> $rawData
     */
    private static function fromReactionPayload(array $payload, array $rawData): self
    {
        return new self(
            messageId: (string) ($payload['id'] ?? ''),
            from: (string) ($payload['from'] ?? $payload['chat_id'] ?? ''),
            chatId: (string) ($payload['chat_id'] ?? $payload['from'] ?? ''),
            type: 'reaction',
            text: null,
            mediaId: null,
            mimeType: null,
            filename: null,
            caption: null,
            location: null,
            contacts: null,
            reaction: [
                'emoji' => $payload['reaction'] ?? null,
                'messageId' => $payload['reacted_message_id'] ?? null,
            ],
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

        if (! empty($payload['contact'])) {
            return 'contacts';
        }

        if (isset($payload['image'])) {
            return 'image';
        }

        if (isset($payload['video'])) {
            return 'video';
        }

        if (isset($payload['audio'])) {
            return 'audio';
        }

        if (isset($payload['document'])) {
            return 'document';
        }

        if (isset($payload['sticker'])) {
            return 'image';
        }

        if (isset($payload['video_note'])) {
            return 'video';
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
        $empty = ['id' => null, 'mimeType' => null, 'filename' => null, 'caption' => null];

        foreach (['image', 'video', 'audio', 'document', 'sticker', 'video_note'] as $mediaType) {
            if (! isset($payload[$mediaType])) {
                continue;
            }

            $media = $payload[$mediaType];

            if (is_string($media)) {
                return [
                    'id' => $media,
                    'mimeType' => self::mimeTypeFromPath($media, $mediaType),
                    'filename' => basename($media),
                    'caption' => isset($payload['body']) ? (string) $payload['body'] : null,
                ];
            }

            if (is_array($media)) {
                $url = isset($media['url']) ? (string) $media['url'] : null;

                return [
                    'id' => $url,
                    'mimeType' => self::mimeTypeFromPath($url ?? '', $mediaType),
                    'filename' => isset($media['filename']) ? (string) $media['filename'] : ($url !== null ? basename($url) : null),
                    'caption' => isset($media['caption']) ? (string) $media['caption'] : (isset($payload['body']) ? (string) $payload['body'] : null),
                ];
            }
        }

        return $empty;
    }

    private static function mimeTypeFromPath(string $path, string $mediaType): string
    {
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        return match ($ext) {
            'jpg', 'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'webp' => 'image/webp',
            'gif' => 'image/gif',
            'mp4' => 'video/mp4',
            'webm' => 'video/webm',
            'ogg' => 'audio/ogg',
            'mp3' => 'audio/mpeg',
            'aac' => 'audio/aac',
            'pdf' => 'application/pdf',
            default => match ($mediaType) {
                'image', 'sticker' => 'image/jpeg',
                'video', 'video_note' => 'video/mp4',
                'audio' => 'audio/ogg',
                default => 'application/octet-stream',
            },
        };
    }

    /**
     * @param array<string, mixed> $payload
     *
     * @return array<int, string>|null
     */
    private static function extractContacts(array $payload): ?array
    {
        $contact = $payload['contact'] ?? null;

        if (! is_array($contact)) {
            return null;
        }

        $vcard = isset($contact['vcard']) ? (string) $contact['vcard'] : null;

        return $vcard !== null ? [$vcard] : null;
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
}
