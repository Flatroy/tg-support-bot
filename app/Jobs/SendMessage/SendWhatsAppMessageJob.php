<?php

namespace App\Jobs\SendMessage;

use App\DTOs\TelegramUpdateDto;
use App\DTOs\WhatsApp\WhatsAppTextMessageDto;
use App\Models\BotUser;
use App\Models\Message;
use App\Models\WhatsappMessage;
use App\WhatsAppBot\WhatsAppMethods;
use Illuminate\Support\Facades\Log;

class SendWhatsAppMessageJob extends AbstractSendMessageJob
{
    public int $tries = 5;

    public int $timeout = 20;

    public int $botUserId;

    public mixed $updateDto;

    public mixed $queryParams;

    public string $typeMessage = 'outgoing';

    public function __construct(
        int $botUserId,
        TelegramUpdateDto $updateDto,
        WhatsAppTextMessageDto $queryParams,
    ) {
        $this->botUserId = $botUserId;
        $this->updateDto = $updateDto;
        $this->queryParams = $queryParams;
    }

    public function handle(): void
    {
        try {
            $botUser = BotUser::find($this->botUserId);

            $response = WhatsAppMethods::sendMessage($this->queryParams);

            if ($response->response_code >= 200 && $response->response_code < 300 && !empty($response->message_id)) {
                $this->saveMessage($botUser, $response);
                $this->updateTopic($botUser, $this->typeMessage);

                return;
            }

            if (!empty($response->error_message)) {
                throw new \Exception($response->error_message, 1);
            }

            throw new \Exception('SendWhatsAppMessageJob: unknown error', 1);
        } catch (\Throwable $e) {
            Log::channel('loki')->log($e->getCode() === 1 ? 'warning' : 'error', $e->getMessage(), ['file' => $e->getFile(), 'line' => $e->getLine()]);
        }
    }

    /**
     * Save message to database after successful sending.
     *
     * @param BotUser $botUser
     * @param mixed   $resultQuery
     *
     * @return void
     */
    protected function saveMessage(BotUser $botUser, mixed $resultQuery): void
    {
        $waMessage = WhatsappMessage::create([
            'wa_message_id' => $resultQuery->message_id,
        ]);

        $message = Message::create([
            'bot_user_id' => $botUser->id,
            'platform' => $botUser->platform,
            'message_type' => $this->typeMessage,
            'from_id' => $this->updateDto->messageId,
            'to_id' => $waMessage->id,
        ]);

        $waMessage->update(['message_id' => $message->id]);
    }

    /**
     * Edit message in database.
     *
     * @param BotUser $botUser
     * @param mixed   $resultQuery
     *
     * @return void
     */
    protected function editMessage(BotUser $botUser, mixed $resultQuery): void
    {
        //
    }
}
