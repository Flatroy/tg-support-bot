<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\DTOs\WhatsApp\GowaUpdateDto;
use App\DTOs\WhatsApp\WhatsAppTextMessageDto;
use App\DTOs\WhatsApp\WhatsAppUpdateDto;
use App\Models\BotUser;
use App\Models\WhatsappMessage;
use App\Services\WhatsApp\Providers\GowaProvider;
use App\Services\WhatsApp\WhatsAppMessageService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class GowaBotController
{
    public function bot_query(Request $request): Response
    {
        if (config('app.debug')) {
            Log::debug('Received GOWA webhook: ' . $request->getContent());
        }

        /** @var array<string, mixed> $data */
        $data = $request->all();

        if (($data['event'] ?? null) === 'group.joined') {
            $this->handleGroupJoined($data);

            return $this->okResponse();
        }

        $dataHook = GowaUpdateDto::fromRequest($request);

        if ($dataHook === null) {
            return $this->okResponse();
        }

        if ($this->isDuplicatedEvent($dataHook->messageId)) {
            return $this->okResponse();
        }

        $this->markAsRead($dataHook->messageId, $dataHook->from);

        $chatId = $this->extractPhoneNumber($dataHook->chatId);
        $botUser = BotUser::getUserByChatId($chatId, 'whatsapp');

        if ($botUser === null) {
            abort(502, 'Error');
        }

        if ($botUser->isBanned()) {
            $this->sendBannedMessage($botUser);

            return $this->okResponse();
        }

        if ($dataHook->type === 'reaction') {
            return $this->okResponse();
        }

        if ($dataHook->type !== 'text') {
            Log::info('GOWA media webhook', [
                'type' => $dataHook->type,
                'mediaId' => $dataHook->mediaId,
                'chatId' => $dataHook->chatId,
                'raw_type' => $dataHook->rawData['payload']['type'] ?? null,
            ]);
        }

        // Sync recent chat history to catch any missed messages (replies, etc.)
        $this->syncChatHistory($dataHook->chatId, $botUser);

        (new WhatsAppMessageService($this->convertToWhatsAppUpdateDto($dataHook)))->handleUpdate();

        return $this->okResponse();
    }

    /**
     * @param array<string, mixed> $data
     */
    private function handleGroupJoined(array $data): void
    {
        try {
            $payload = $data['payload'] ?? [];
            $chatId = isset($payload['chat_id']) ? (string) $payload['chat_id'] : null;
            $groupName = isset($payload['group_name']) ? (string) $payload['group_name'] : null;

            if ($chatId === null || $groupName === null) {
                return;
            }

            $numericChatId = $this->extractPhoneNumber($chatId);
            $botUser = BotUser::getUserByChatId($numericChatId, 'whatsapp');

            if ($botUser === null) {
                return;
            }

            WhatsappMessage::create([
                'wa_message_id' => 'group_joined_' . $numericChatId,
                'sender_name' => $groupName,
            ]);
        } catch (\Throwable $exception) {
            Log::warning('Failed to handle GOWA group.joined: ' . $exception->getMessage());
        }
    }

    private function markAsRead(string $messageId, string $from): void
    {
        try {
            $this->provider()->markAsRead($messageId);
        } catch (\Throwable $exception) {
            Log::warning('Failed to mark GOWA message as read: ' . $exception->getMessage());
        }
    }

    private function sendBannedMessage(BotUser $botUser): void
    {
        $this->provider()->sendMessage(WhatsAppTextMessageDto::from([
            'to' => (string) $botUser->chat_id,
            'type' => 'text',
            'text' => __('messages.ban_user'),
        ]));
    }

    private function extractPhoneNumber(string $jid): string
    {
        return explode('@', $jid)[0];
    }

    private function convertToWhatsAppUpdateDto(GowaUpdateDto $gowaDto): WhatsAppUpdateDto
    {
        return new WhatsAppUpdateDto(
            messageId: $gowaDto->messageId,
            from: $this->extractPhoneNumber($gowaDto->from),
            chatId: $this->extractPhoneNumber($gowaDto->chatId),
            type: $gowaDto->type,
            text: $gowaDto->text,
            mediaId: $gowaDto->mediaId,
            mimeType: $gowaDto->mimeType,
            filename: $gowaDto->filename,
            caption: $gowaDto->caption,
            location: $gowaDto->location,
            contacts: $gowaDto->contacts,
            reaction: $gowaDto->reaction,
            status: null,
            rawData: $gowaDto->rawData,
            senderName: $gowaDto->senderName,
        );
    }

    private function okResponse(): Response
    {
        return response('ok', 200);
    }

    private function isDuplicatedEvent(string $messageId): bool
    {
        $cacheKey = 'gowa_event_' . $messageId;

        if (Cache::has($cacheKey)) {
            return true;
        }

        Cache::put($cacheKey, true, 600);

        return false;
    }

    private function provider(): GowaProvider
    {
        return new GowaProvider();
    }

    /**
     * Sync recent chat history to catch missed messages (replies, etc.).
     * Processes last 10 messages, skipping those already in our database.
     */
    private function syncChatHistory(string $chatJid, BotUser $botUser): void
    {
        try {
            // Use a cache key to prevent syncing the same chat too frequently
            $syncKey = 'gowa_sync_' . md5($chatJid);
            if (Cache::has($syncKey)) {
                return;
            }
            Cache::put($syncKey, true, 30); // Sync once per 30 seconds per chat

            $messages = $this->provider()->getChatMessages($chatJid, 10);

            if (empty($messages)) {
                return;
            }

            // Get existing message IDs from our database
            $messageIds = array_column($messages, 'id');
            $existingIds = WhatsappMessage::query()
                ->whereIn('wa_message_id', $messageIds)
                ->pluck('wa_message_id')
                ->toArray();
            $existingSet = array_flip($existingIds);

            // Process messages in reverse order (oldest first) so they appear correctly in Telegram
            $messagesToProcess = [];
            foreach (array_reverse($messages) as $msg) {
                $msgId = $msg['id'] ?? null;
                if ($msgId === null || isset($existingSet[$msgId])) {
                    continue; // Skip existing messages
                }

                // Skip our own messages (from bot/device)
                if (! empty($msg['is_from_me'])) {
                    continue;
                }

                // Skip reactions
                if (($msg['type'] ?? '') === 'reaction') {
                    continue;
                }

                $messagesToProcess[] = $msg;
            }

            if (empty($messagesToProcess)) {
                return;
            }

            Log::info('GOWA syncing chat history', [
                'chat' => $chatJid,
                'new_messages' => count($messagesToProcess),
            ]);

            foreach ($messagesToProcess as $msg) {
                $this->processHistoryMessage($msg, $chatJid, $botUser);
            }
        } catch (\Throwable $exception) {
            Log::warning('Failed to sync chat history: ' . $exception->getMessage());
        }
    }

    /**
     * Process a single message from chat history.
     *
     * @param array<string, mixed> $msg
     */
    private function processHistoryMessage(array $msg, string $chatJid, BotUser $botUser): void
    {
        try {
            $msgId = $msg['id'] ?? null;
            if ($msgId === null) {
                return;
            }

            // Build a payload similar to webhook format for GowaUpdateDto
            $payload = [
                'id' => $msgId,
                'chat_id' => $chatJid,
                'from' => $msg['sender'] ?? '',
                'is_from_me' => $msg['is_from_me'] ?? false,
                'type' => $msg['type'] ?? 'text',
                'body' => $msg['body'] ?? '',
                'timestamp' => $msg['timestamp'] ?? time(),
                'sender_name' => $msg['push_name'] ?? null,
            ];

            // Add media fields if present
            if (! empty($msg['media_type'])) {
                $payload[$msg['media_type']] = $msg['url'] ?? $msg['path'] ?? '';
                $payload['body'] = $msg['caption'] ?? $payload['body'];
            }

            $request = \Illuminate\Http\Request::create('/', 'POST', [
                'event' => 'message',
                'device_id' => $chatJid,
                'payload' => $payload,
            ]);

            $dataHook = GowaUpdateDto::fromRequest($request);

            if ($dataHook === null) {
                return;
            }

            // Skip if already processed recently (double-check)
            if ($this->isDuplicatedEvent($dataHook->messageId)) {
                return;
            }

            (new WhatsAppMessageService($this->convertToWhatsAppUpdateDto($dataHook)))->handleUpdate();
        } catch (\Throwable $exception) {
            Log::warning('Failed to process history message: ' . $exception->getMessage(), ['msg_id' => $msg['id'] ?? 'unknown']);
        }
    }
}
