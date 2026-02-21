<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\DTOs\WhatsApp\WahaUpdateDto;
use App\DTOs\WhatsApp\WhatsAppTextMessageDto;
use App\DTOs\WhatsApp\WhatsAppUpdateDto;
use App\Models\BotUser;
use App\Services\WhatsApp\Providers\WahaProvider;
use App\Services\WhatsApp\WhatsAppMessageService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class WahaBotController
{
    public function bot_query(Request $request): Response
    {
        if (config('app.debug')) {
            Log::debug('Received WAHA webhook: ' . $request->getContent());
        }

        $dataHook = WahaUpdateDto::fromRequest($request);

        if ($dataHook === null) {
            return $this->okResponse();
        }

        if ($this->isDuplicatedEvent($dataHook->messageId)) {
            return $this->okResponse();
        }

        if ($dataHook->type === 'status') {
            $this->handleStatus($dataHook);

            return $this->okResponse();
        }

        $this->markAsRead($dataHook->messageId);

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
            $this->handleReaction($dataHook, $botUser);

            return $this->okResponse();
        }

        (new WhatsAppMessageService($this->convertToWhatsAppUpdateDto($dataHook)))->handleUpdate();

        return $this->okResponse();
    }

    private function handleStatus(WahaUpdateDto $dataHook): void
    {
    }

    private function handleReaction(WahaUpdateDto $dataHook, BotUser $botUser): void
    {
    }

    private function sendBannedMessage(BotUser $botUser): void
    {
        $this->provider()->sendMessage(WhatsAppTextMessageDto::from([
            'to' => (string) $botUser->chat_id,
            'type' => 'text',
            'text' => __('messages.ban_user'),
        ]));
    }

    private function markAsRead(string $messageId): void
    {
        try {
            $this->provider()->markAsRead($messageId);
        } catch (\Throwable $exception) {
            Log::warning('Failed to mark WAHA message as read: ' . $exception->getMessage());
        }
    }

    private function extractPhoneNumber(string $chatId): string
    {
        return explode('@', $chatId)[0];
    }

    private function convertToWhatsAppUpdateDto(WahaUpdateDto $wahaDto): WhatsAppUpdateDto
    {
        return new WhatsAppUpdateDto(
            messageId: $wahaDto->messageId,
            from: $this->extractPhoneNumber($wahaDto->from),
            chatId: $this->extractPhoneNumber($wahaDto->chatId),
            type: $wahaDto->type,
            text: $wahaDto->text,
            mediaId: $wahaDto->mediaId,
            mimeType: $wahaDto->mimeType,
            filename: $wahaDto->filename,
            caption: $wahaDto->caption,
            location: $wahaDto->location,
            contacts: $wahaDto->contacts,
            reaction: $wahaDto->reaction,
            status: $wahaDto->status,
            rawData: $wahaDto->rawData,
        );
    }

    private function okResponse(): Response
    {
        return response('ok', 200);
    }

    private function isDuplicatedEvent(string $messageId): bool
    {
        $cacheKey = 'waha_event_' . $messageId;

        if (Cache::has($cacheKey)) {
            return true;
        }

        Cache::put($cacheKey, true, 600);

        return false;
    }

    private function provider(): WahaProvider
    {
        return new WahaProvider();
    }
}
