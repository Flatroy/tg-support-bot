<?php

declare(strict_types=1);

namespace Tests\Feature\Services;

use App\DTOs\TelegramUpdateDto;
use App\Jobs\SendMessage\SendWhatsAppMessageJob;
use App\Models\BotUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class TgWhatsAppMessageServiceTest extends TestCase
{
    use RefreshDatabase;

    private ?BotUser $botUser;

    public function setUp(): void
    {
        parent::setUp();

        Queue::fake();

        config([
            'traffic_source.settings.whatsapp.token' => 'test_token',
            'traffic_source.settings.whatsapp.phone_number_id' => '123456789',
            'traffic_source.settings.whatsapp.api_version' => 'v22.0',
            'traffic_source.settings.telegram.token' => 'test_tg_token',
            'traffic_source.settings.telegram.group_id' => '-1001234567890',
        ]);

        $this->botUser = BotUser::create([
            'chat_id' => '15559876543',
            'platform' => 'whatsapp',
            'topic_id' => 999,
        ]);
    }

    public function test_send_photo_dispatches_job_with_media_id(): void
    {
        Http::fake([
            '*' => Http::sequence()
                ->push(['ok' => true, 'result' => ['file_path' => 'photos/test.jpg']], 200)
                ->push('fake image bytes', 200)
                ->push(['id' => 'wa_media_uploaded'], 200),
        ]);

        $update = $this->makePhotoUpdate();
        $service = new \App\Services\TgWhatsApp\TgWhatsAppMessageService($update);
        $service->handleUpdate();

        Queue::assertPushed(SendWhatsAppMessageJob::class, function ($job) {
            return $job->queryParams->type === 'image'
                && $job->queryParams->mediaId === 'wa_media_uploaded';
        });
    }

    public function test_send_document_dispatches_job_with_filename(): void
    {
        Http::fake([
            '*' => Http::response([
                'ok' => true,
                'result' => ['file_path' => 'documents/report.pdf'],
            ], 200),
        ]);

        $update = $this->makeDocumentUpdate('report.pdf');
        $service = new \App\Services\TgWhatsApp\TgWhatsAppMessageService($update);
        $service->handleUpdate();

        Queue::assertPushed(SendWhatsAppMessageJob::class, function ($job) {
            return $job->queryParams->type === 'document'
                && $job->queryParams->filename === 'report.pdf';
        });
    }

    public function test_send_document_preserves_special_characters_in_filename(): void
    {
        Http::fake([
            '*' => Http::response([
                'ok' => true,
                'result' => ['file_path' => 'documents/file.ovpn'],
            ], 200),
        ]);

        $update = $this->makeDocumentUpdate('Ireland 🇮🇪 .ovpn');
        $service = new \App\Services\TgWhatsApp\TgWhatsAppMessageService($update);
        $service->handleUpdate();

        Queue::assertPushed(SendWhatsAppMessageJob::class, function ($job) {
            return $job->queryParams->filename === 'Ireland 🇮🇪 .ovpn';
        });
    }

    public function test_send_voice_dispatches_job_with_media_id(): void
    {
        Http::fake([
            '*' => Http::sequence()
                ->push(['ok' => true, 'result' => ['file_path' => 'voice/test.ogg']], 200)
                ->push('fake ogg bytes', 200)
                ->push(['id' => 'wa_voice_uploaded'], 200),
        ]);

        $update = $this->makeVoiceUpdate();
        $service = new \App\Services\TgWhatsApp\TgWhatsAppMessageService($update);
        $service->handleUpdate();

        Queue::assertPushed(SendWhatsAppMessageJob::class, function ($job) {
            return $job->queryParams->type === 'audio'
                && $job->queryParams->mediaId === 'wa_voice_uploaded';
        });
    }

    public function test_send_text_dispatches_job(): void
    {
        $update = $this->makeTextUpdate('Hello from Telegram');
        $service = new \App\Services\TgWhatsApp\TgWhatsAppMessageService($update);
        $service->handleUpdate();

        Queue::assertPushed(SendWhatsAppMessageJob::class, function ($job) {
            return $job->queryParams->type === 'text'
                && $job->queryParams->text === 'Hello from Telegram';
        });
    }

    public function test_send_location_dispatches_job(): void
    {
        $update = $this->makeLocationUpdate();
        $service = new \App\Services\TgWhatsApp\TgWhatsAppMessageService($update);
        $service->handleUpdate();

        Queue::assertPushed(SendWhatsAppMessageJob::class, function ($job) {
            return $job->queryParams->type === 'location'
                && $job->queryParams->latitude === 55.7558
                && $job->queryParams->longitude === 37.6173;
        });
    }

    public function test_send_sticker_dispatches_text_with_emoji(): void
    {
        $update = $this->makeStickerUpdate('😀');
        $service = new \App\Services\TgWhatsApp\TgWhatsAppMessageService($update);
        $service->handleUpdate();

        Queue::assertPushed(SendWhatsAppMessageJob::class, function ($job) {
            return $job->queryParams->type === 'text'
                && $job->queryParams->text === '😀';
        });
    }

    public function test_send_contact_dispatches_text_job(): void
    {
        $update = $this->makeContactUpdate();
        $service = new \App\Services\TgWhatsApp\TgWhatsAppMessageService($update);
        $service->handleUpdate();

        Queue::assertPushed(SendWhatsAppMessageJob::class, function ($job) {
            return $job->queryParams->type === 'text'
                && str_contains($job->queryParams->text, 'John')
                && str_contains($job->queryParams->text, '+1234567890');
        });
    }

    private function makePhotoUpdate(): TelegramUpdateDto
    {
        return $this->makeTelegramUpdate([
            'photo' => [
                ['file_id' => 'photo_small', 'file_unique_id' => 'u1', 'width' => 90, 'height' => 90, 'file_size' => 1000],
                ['file_id' => 'photo_large', 'file_unique_id' => 'u2', 'width' => 800, 'height' => 600, 'file_size' => 50000],
            ],
            'caption' => 'Test photo caption',
        ]);
    }

    private function makeDocumentUpdate(string $filename): TelegramUpdateDto
    {
        return $this->makeTelegramUpdate([
            'document' => [
                'file_id' => 'doc_file_id',
                'file_unique_id' => 'doc_unique',
                'file_name' => $filename,
                'mime_type' => 'application/pdf',
                'file_size' => 1024,
            ],
        ]);
    }

    private function makeVoiceUpdate(): TelegramUpdateDto
    {
        return $this->makeTelegramUpdate([
            'voice' => [
                'file_id' => 'voice_file_id',
                'file_unique_id' => 'voice_unique',
                'duration' => 5,
                'mime_type' => 'audio/ogg',
                'file_size' => 2048,
            ],
        ]);
    }

    private function makeTextUpdate(string $text): TelegramUpdateDto
    {
        return $this->makeTelegramUpdate([
            'text' => $text,
        ]);
    }

    private function makeLocationUpdate(): TelegramUpdateDto
    {
        return $this->makeTelegramUpdate([
            'location' => [
                'latitude' => 55.7558,
                'longitude' => 37.6173,
            ],
        ]);
    }

    private function makeStickerUpdate(string $emoji): TelegramUpdateDto
    {
        return $this->makeTelegramUpdate([
            'sticker' => [
                'file_id' => 'sticker_file_id',
                'file_unique_id' => 'sticker_unique',
                'type' => 'regular',
                'width' => 512,
                'height' => 512,
                'emoji' => $emoji,
            ],
        ]);
    }

    private function makeContactUpdate(): TelegramUpdateDto
    {
        return $this->makeTelegramUpdate([
            'contact' => [
                'phone_number' => '+1234567890',
                'first_name' => 'John',
            ],
        ]);
    }

    private function makeTelegramUpdate(array $messageExtra): TelegramUpdateDto
    {
        $data = [
            'update_id' => time(),
            'message' => array_merge([
                'message_id' => time(),
                'from' => [
                    'id' => time(),
                    'is_bot' => false,
                    'first_name' => 'Admin',
                    'username' => 'admin',
                ],
                'chat' => [
                    'id' => config('traffic_source.settings.telegram.group_id'),
                    'title' => 'Support Group',
                    'is_forum' => true,
                    'type' => 'supergroup',
                ],
                'date' => time(),
                'message_thread_id' => $this->botUser->topic_id,
                'is_topic_message' => true,
            ], $messageExtra),
        ];

        $request = \Illuminate\Support\Facades\Request::create('api/telegram/bot', 'POST', $data);

        return TelegramUpdateDto::fromRequest($request);
    }
}
