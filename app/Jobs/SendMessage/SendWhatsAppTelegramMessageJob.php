<?php

namespace App\Jobs\SendMessage;

use App\DTOs\TelegramAnswerDto;
use App\DTOs\TGTextMessageDto;
use App\DTOs\WhatsApp\WhatsAppUpdateDto;
use App\Jobs\TopicCreateJob;
use App\Logging\LokiLogger;
use App\Models\BotUser;
use App\Models\Message;
use App\Models\WhatsappMessage;
use App\TelegramBot\TelegramMethods;

class SendWhatsAppTelegramMessageJob extends AbstractSendMessageJob
{
    public int $tries = 5;

    public int $timeout = 20;

    public int $botUserId;

    public mixed $updateDto;

    public mixed $queryParams;

    public string $typeMessage = 'incoming';

    private mixed $telegramMethods;

    public function __construct(
        int $botUserId,
        WhatsAppUpdateDto $updateDto,
        TGTextMessageDto $queryParams,
        mixed $telegramMethods = null,
    ) {
        $this->botUserId = $botUserId;
        $this->updateDto = $updateDto;
        $this->queryParams = $queryParams;

        $this->telegramMethods = $telegramMethods ?? new TelegramMethods();
    }

    public function handle(): void
    {
        try {
            $botUser = BotUser::find($this->botUserId);

            $methodQuery = $this->queryParams->methodQuery;
            $params = $this->queryParams->toArray();

            if ($botUser->topic_id) {
                $response = $this->telegramMethods->sendQueryTelegram(
                    'editForumTopic',
                    [
                        'chat_id' => config('traffic_source.settings.telegram.group_id'),
                        'message_thread_id' => $botUser->topic_id,
                        'icon_custom_emoji_id' => __('icons.incoming'),
                    ]
                );

                if ($response->isTopicNotFound || $response->type_error === 'TOPIC_NOT_MODIFIED') {
                    $botUser->update([
                        'topic_id' => null,
                    ]);

                    $botUser->refresh();
                } else {
                    $params['message_thread_id'] = $botUser->topic_id;
                }
            }

            if (!$botUser->topic_id) {
                TopicCreateJob::withChain([
                    new SendWhatsAppTelegramMessageJob(
                        $this->botUserId,
                        $this->updateDto,
                        $this->queryParams,
                    ),
                ])->dispatch($this->botUserId);

                return;
            }

            $response = $this->telegramMethods->sendQueryTelegram(
                $methodQuery,
                $params,
                $this->queryParams->token
            );

            if ($response->ok === true) {
                if ($methodQuery !== 'editMessageText' && $methodQuery !== 'editMessageCaption') {
                    $this->saveMessage($botUser, $response);
                    $this->updateTopic($botUser, $this->typeMessage);

                    return;
                }
            } else {
                $this->telegramResponseHandler($response);
            }
        } catch (\Throwable $e) {
            (new LokiLogger())->logException($e);
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
        if (!$resultQuery instanceof TelegramAnswerDto) {
            throw new \Exception('Expected TelegramAnswerDto', 1);
        }

        $waMessage = WhatsappMessage::create([
            'wa_message_id' => $this->updateDto->messageId,
        ]);

        $message = Message::create([
            'bot_user_id' => $botUser->id,
            'platform' => $botUser->platform,
            'message_type' => $this->typeMessage,
            'from_id' => $waMessage->id,
            'to_id' => $resultQuery->message_id,
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
