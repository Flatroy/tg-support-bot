<?php

namespace Tests\Feature\Jobs;

use App\Actions\Telegram\DeleteForumTopic;
use App\DTOs\TGTextMessageDto;
use App\DTOs\WhatsApp\WhatsAppUpdateDto;
use App\Jobs\SendMessage\SendWhatsAppTelegramMessageJob;
use App\Models\BotUser;
use App\Models\Message;
use App\Models\WhatsappMessage;
use App\TelegramBot\TelegramMethods;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\Mocks\Tg\Answer\TelegramAnswerDtoMock;
use Tests\Mocks\WhatsApp\WhatsAppUpdateDtoMock;
use Tests\TestCase;

class SendWhatsAppTelegramMessageJobTest extends TestCase
{
    use RefreshDatabase;

    private WhatsAppUpdateDto $dto;

    private ?BotUser $botUser;

    public function setUp(): void
    {
        parent::setUp();

        Message::truncate();
        WhatsappMessage::truncate();
        Queue::fake();

        $this->dto = WhatsAppUpdateDtoMock::getDto();
        $this->botUser = BotUser::getUserByChatId($this->dto->from, 'whatsapp');
        $this->botUser->topic_id = 123;
        $this->botUser->save();
    }

    public function test_send_message_for_user(): void
    {
        try {
            $typeMessage = 'incoming';
            $textMessage = 'Тестовое сообщение';
            $dtoParams = TelegramAnswerDtoMock::getDtoParams();

            $dtoParams['result']['text'] = $textMessage;
            $dto = TelegramAnswerDtoMock::getDto($dtoParams);

            $mockTelegramMethods = \Mockery::mock(TelegramMethods::class);
            $mockTelegramMethods->shouldReceive('sendQueryTelegram')->andReturn($dto);

            $queryParams = TGTextMessageDto::from([
                'methodQuery' => 'sendMessage',
                'chat_id' => $this->botUser->chat_id,
                'text' => $textMessage,
            ]);

            $job = new SendWhatsAppTelegramMessageJob(
                $this->botUser->id,
                $this->dto,
                $queryParams,
                $mockTelegramMethods
            );
            $job->handle();

            $this->assertDatabaseHas('messages', [
                'bot_user_id' => $this->botUser->id,
                'message_type' => $typeMessage,
            ]);

            $this->assertDatabaseCount('whatsapp_messages', 1);

            $waMessage = WhatsappMessage::first();
            $this->assertNotNull($waMessage);
            $this->assertNotEmpty($waMessage->wa_message_id);
            $this->assertStringStartsWith('wamid.', $waMessage->wa_message_id);

            $message = Message::first();
            $this->assertEquals($waMessage->id, $message->from_id);
            $this->assertEquals($message->id, $waMessage->message_id);
        } finally {
            if ($this->botUser->topic_id) {
                DeleteForumTopic::execute($this->botUser);
            }
        }
    }
}
