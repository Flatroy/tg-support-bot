<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\DTOs\WhatsApp\WhatsAppTextMessageDto;
use App\DTOs\WhatsApp\WhatsAppUpdateDto;
use App\Models\BotUser;
use App\Services\WhatsApp\WhatsAppMessageService;
use App\WhatsAppBot\WhatsAppMethods;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;

class WhatsAppBotController
{
    /**
     * Webhook verification endpoint (GET).
     *
     * @param Request $request
     *
     * @return Response
     */
    public function verify(Request $request): Response
    {
        $mode = $request->query('hub_mode');
        $token = $request->query('hub_verify_token');
        $challenge = $request->query('hub_challenge');

        if ($mode === 'subscribe' && $token === config('traffic_source.settings.whatsapp.verify_token')) {
            return response($challenge, 200);
        }

        return response('Forbidden', 403);
    }

    /**
     * Webhook handler for incoming messages (POST).
     *
     * @param Request $request
     *
     * @return Response
     */
    public function bot_query(Request $request): Response
    {
        $dataHook = WhatsAppUpdateDto::fromRequest($request);
        if (empty($dataHook)) {
            return response('ok', 200);
        }

        $cacheKey = 'wa_event_' . $dataHook->messageId;
        if (Cache::has($cacheKey)) {
            return response('ok', 200);
        }
        Cache::put($cacheKey, true, 600);

        if ($dataHook->type === 'status') {
            $this->handleStatus($dataHook);

            return response('ok', 200);
        }

        WhatsAppMethods::markAsRead($dataHook->messageId);

        $botUser = BotUser::getUserByChatId($dataHook->from, 'whatsapp');

        if ($botUser->isBanned()) {
            $this->sendBannedMessage($botUser);

            return response('ok', 200);
        }

        if ($dataHook->type === 'reaction') {
            $this->handleReaction($dataHook, $botUser);

            return response('ok', 200);
        }

        (new WhatsAppMessageService($dataHook))->handleUpdate();

        return response('ok', 200);
    }

    /**
     * Handle status updates (read receipts, delivered, sent).
     *
     * @param WhatsAppUpdateDto $dataHook
     *
     * @return void
     */
    private function handleStatus(WhatsAppUpdateDto $dataHook): void
    {
        // TODO: Forward read receipts to TG group
    }

    /**
     * Handle reaction messages.
     *
     * @param WhatsAppUpdateDto $dataHook
     * @param BotUser           $botUser
     *
     * @return void
     */
    private function handleReaction(WhatsAppUpdateDto $dataHook, BotUser $botUser): void
    {
        // TODO: Forward reactions to TG group as text messages
    }

    /**
     * @param BotUser $botUser
     *
     * @return void
     */
    private function sendBannedMessage(BotUser $botUser): void
    {
        WhatsAppMethods::sendMessage(WhatsAppTextMessageDto::from([
            'to' => (string) $botUser->chat_id,
            'type' => 'text',
            'text' => __('messages.ban_user'),
        ]));
    }
}
