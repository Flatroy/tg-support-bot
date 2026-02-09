<?php

namespace Tests\Feature\Jobs;

use App\Actions\Telegram\DeleteForumTopic;
use App\DTOs\TelegramUpdateDto;
use App\DTOs\WhatsApp\WhatsAppTextMessageDto;
use App\Jobs\SendMessage\SendWhatsAppMessageJob;
use App\Jobs\TopicCreateJob;
use App\Models\BotUser;
use App\Models\Message;
use App\Models\WhatsappMessage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\Mocks\Tg\TelegramUpdateDto_WhatsAppMock;
use Tests\Mocks\WhatsApp\Answer\WhatsAppAnswerDtoMock;
use Tests\TestCase;

class SendWhatsAppMessageJobTest extends TestCase
{
    use RefreshDatabase;

    private TelegramUpdateDto $dto;

    private ?BotUser $botUser;

    public function setUp(): void
    {
        parent::setUp();

        Message::truncate();
        WhatsappMessage::truncate();

        $this->dto = TelegramUpdateDto_WhatsAppMock::getDto();

        $chatId = time();
        $this->botUser = BotUser::getUserByChatId($chatId, 'whatsapp');

        $jobTopicCreate = new TopicCreateJob(
            $this->botUser->id,
        );
        $jobTopicCreate->handle();

        $this->botUser->refresh();
    }

    protected function tearDown(): void
    {
        if (isset($this->botUser->topic_id)) {
            DeleteForumTopic::execute($this->botUser);
        }

        parent::tearDown();
    }

    public function test_send_message_for_user(): void
    {
        try {
            $typeMessage = 'outgoing';
            $textMessage = 'Тестовое сообщение';

            $waResponse = WhatsAppAnswerDtoMock::getDtoParams();
            Http::fake([
                'graph.facebook.com/*' => Http::response($waResponse, 200),
            ]);

            $queryParams = WhatsAppTextMessageDto::from([
                'to' => (string) $this->botUser->chat_id,
                'type' => 'text',
                'text' => $textMessage,
            ]);

            $job = new SendWhatsAppMessageJob(
                $this->botUser->id,
                $this->dto,
                $queryParams,
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
            $this->assertEquals($waMessage->id, $message->to_id);
            $this->assertEquals($message->id, $waMessage->message_id);
        } finally {
            if ($this->botUser->topic_id) {
                DeleteForumTopic::execute($this->botUser);
            }
        }
    }
}
