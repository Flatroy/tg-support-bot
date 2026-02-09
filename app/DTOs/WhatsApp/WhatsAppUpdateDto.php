<?php

declare(strict_types=1);

namespace App\DTOs\WhatsApp;

use Illuminate\Http\Request;

readonly class WhatsAppUpdateDto
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
            $entry = $data['entry'][0] ?? null;
            if (empty($entry)) {
                return null;
            }

            $change = $entry['changes'][0] ?? null;
            if (empty($change) || ($change['field'] ?? '') !== 'messages') {
                return null;
            }

            $value = $change['value'] ?? [];

            if (!empty($value['statuses'])) {
                return self::fromStatus($value['statuses'][0], $data);
            }

            $message = $value['messages'][0] ?? null;
            if (empty($message)) {
                return null;
            }

            return self::fromMessage($message, $data);
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * @param array $message
     * @param array $rawData
     *
     * @return self
     */
    private static function fromMessage(array $message, array $rawData): self
    {
        $type = $message['type'] ?? 'text';

        return new self(
            messageId: $message['id'],
            from: $message['from'],
            chatId: $message['from'],
            type: $type,
            text: self::extractText($message, $type),
            mediaId: self::extractMediaId($message, $type),
            mimeType: self::extractMimeType($message, $type),
            filename: self::extractFilename($message, $type),
            caption: self::extractCaption($message, $type),
            location: $message['location'] ?? null,
            contacts: $message['contacts'] ?? null,
            reaction: $message['reaction'] ?? null,
            status: null,
            rawData: $rawData,
        );
    }

    /**
     * @param array $statusData
     * @param array $rawData
     *
     * @return self
     */
    private static function fromStatus(array $statusData, array $rawData): self
    {
        return new self(
            messageId: $statusData['id'],
            from: $statusData['recipient_id'],
            chatId: $statusData['recipient_id'],
            type: 'status',
            text: null,
            mediaId: null,
            mimeType: null,
            filename: null,
            caption: null,
            location: null,
            contacts: null,
            reaction: null,
            status: $statusData,
            rawData: $rawData,
        );
    }

    /**
     * @param array  $message
     * @param string $type
     *
     * @return string|null
     */
    private static function extractText(array $message, string $type): ?string
    {
        if ($type === 'text') {
            return $message['text']['body'] ?? null;
        }

        if ($type === 'interactive') {
            return $message['interactive']['button_reply']['title']
                ?? $message['interactive']['list_reply']['title']
                ?? null;
        }

        return null;
    }

    /**
     * @param array  $message
     * @param string $type
     *
     * @return string|null
     */
    private static function extractMediaId(array $message, string $type): ?string
    {
        $mediaTypes = ['image', 'document', 'audio', 'video', 'sticker'];
        if (in_array($type, $mediaTypes)) {
            return $message[$type]['id'] ?? null;
        }

        return null;
    }

    /**
     * @param array  $message
     * @param string $type
     *
     * @return string|null
     */
    private static function extractMimeType(array $message, string $type): ?string
    {
        $mediaTypes = ['image', 'document', 'audio', 'video', 'sticker'];
        if (in_array($type, $mediaTypes)) {
            return $message[$type]['mime_type'] ?? null;
        }

        return null;
    }

    /**
     * @param array  $message
     * @param string $type
     *
     * @return string|null
     */
    private static function extractFilename(array $message, string $type): ?string
    {
        if ($type === 'document') {
            return $message['document']['filename'] ?? null;
        }

        return null;
    }

    private static function extractCaption(array $message, string $type): ?string
    {
        $mediaTypes = ['image', 'document', 'video'];
        if (in_array($type, $mediaTypes)) {
            return $message[$type]['caption'] ?? null;
        }

        return null;
    }
}
