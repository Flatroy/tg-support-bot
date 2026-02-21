<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\DTOs\WhatsApp\WahaUpdateDto;
use App\DTOs\WhatsApp\WhatsAppTextMessageDto;
use App\Models\BotUser;
use App\Services\WhatsApp\Providers\WahaProvider;
use App\Services\WhatsApp\WhatsAppMessageService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class WahaBotController
{
    /**
     * Webhook handler for incoming WAHA messages (POST).
     *
     * @param Request $request
     *
     * @return Response
     */
    public function bot_query(Request $request): Response
    {
        if (config('app.debug')) {
            Log::debug('Received WAHA webhook: ' . $request->getContent());
        }

        $dataHook = WahaUpdateDto::fromRequest($request);
        if (empty($dataHook)) {
            return response('ok', 200);
        }

        // Deduplication
        $cacheKey = 'waha_event_' . $dataHook->messageId;
        if (Cache::has($cacheKey)) {
            return response('ok', 200);
        }
        Cache::put($cacheKey, true, 600);

        // Handle status updates
        if ($dataHook->type === 'status') {
            $this->handleStatus($dataHook);

            return response('ok', 200);
        }

        // Mark message as read via WAHA
        $this->markAsRead($dataHook->messageId);

        // Get or create bot user
        $chatId = $this->extractPhoneNumber($dataHook->from);
        $botUser = BotUser::getUserByChatId($chatId, 'whatsapp');

        if ($botUser === null) {
            abort(502, 'Error');
        }

        if ($botUser->isBanned()) {
            $this->sendBannedMessage($botUser);

            return response('ok', 200);
        }

        // Handle reaction messages
        if ($dataHook->type === 'reaction') {
            $this->handleReaction($dataHook, $botUser);

            return response('ok', 200);
        }

        // Process the message using existing service
        // Convert WahaUpdateDto to WhatsAppUpdateDto compatible format
        $updateDto = $this->convertToWhatsAppUpdateDto($dataHook);
        (new WhatsAppMessageService($updateDto))->handleUpdate();

        return response('ok', 200);
    }

    /**
     * Handle status updates (read receipts, delivered, sent).
     *
     * @param WahaUpdateDto $dataHook
     *
     * @return void
     */
    private function handleStatus(WahaUpdateDto $dataHook): void
    {
        // TODO: Forward read receipts to TG group as reactions
        // WAHA ack values: -1=ERROR, 0=PENDING, 1=SERVER, 2=DEVICE, 3=READ, 4=PLAYED
    }

    /**
     * Handle reaction messages.
     *
     * @param WahaUpdateDto $dataHook
     * @param BotUser       $botUser
     *
     * @return void
     */
    private function handleReaction(WahaUpdateDto $dataHook, BotUser $botUser): void
    {
        // TODO: Forward reactions to TG group
    }

    /**
     * Send banned message to user.
     *
     * @param BotUser $botUser
     *
     * @return void
     */
    private function sendBannedMessage(BotUser $botUser): void
    {
        $provider = new WahaProvider();
        $provider->sendMessage(WhatsAppTextMessageDto::from([
            'to' => (string) $botUser->chat_id,
            'type' => 'text',
            'text' => __('messages.ban_user'),
        ]));
    }

    /**
     * Mark a message as read.
     *
     * @param string $messageId
     *
     * @return void
     */
    private function markAsRead(string $messageId): void
    {
        try {
            $provider = new WahaProvider();
            $provider->markAsRead($messageId);
        } catch (\Throwable $e) {
            Log::warning('Failed to mark WAHA message as read: ' . $e->getMessage());
        }
    }

    /**
     * Extract phone number from WAHA chat ID.
     *
     * @param string $chatId
     *
     * @return string
     */
    private function extractPhoneNumber(string $chatId): string
    {
        // WAHA format: 12345678901@c.us or 12345678901@g.us for groups
        $parts = explode('@', $chatId);

        return $parts[0];
    }

    /**
     * Convert WahaUpdateDto to WhatsAppUpdateDto compatible format.
     *
     * @param WahaUpdateDto $wahaDto
     *
     * @return \App\DTOs\WhatsApp\WhatsAppUpdateDto
     */
    private function convertToWhatsAppUpdateDto(WahaUpdateDto $wahaDto): \App\DTOs\WhatsApp\WhatsAppUpdateDto
    {
        // Create a compatible DTO
        return new \App\DTOs\WhatsApp\WhatsAppUpdateDto(
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
}
