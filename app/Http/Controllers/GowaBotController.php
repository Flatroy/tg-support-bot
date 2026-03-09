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

        $chatId = $this->extractPhoneNumber($dataHook->from);
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
}
