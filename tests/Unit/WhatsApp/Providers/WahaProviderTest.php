<?php

declare(strict_types=1);

namespace Tests\Unit\WhatsApp\Providers;

use App\DTOs\WhatsApp\WhatsAppTextMessageDto;
use App\Services\WhatsApp\Providers\WahaProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class WahaProviderTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'traffic_source.settings.whatsapp.provider' => 'waha',
            'traffic_source.settings.whatsapp.waha.base_url' => 'http://localhost:3000',
            'traffic_source.settings.whatsapp.waha.api_key' => 'test-api-key',
            'traffic_source.settings.whatsapp.waha.session' => 'default',
        ]);
    }

    public function test_send_text_message(): void
    {
        Http::fake([
            'localhost:3000/api/sendText' => Http::response([
                'messages' => [
                    ['id' => 'false_12345678901@c.us_ABCDEF'],
                ],
                'timestamp' => time(),
                'from' => 'me',
                'to' => '12345678901@c.us',
                'body' => 'Test message',
            ], 201),
        ]);

        $provider = new WahaProvider();
        $dto = WhatsAppTextMessageDto::from([
            'to' => '12345678901',
            'type' => 'text',
            'text' => 'Test message',
        ]);

        $result = $provider->sendMessage($dto);

        $this->assertEquals(201, $result->response_code);
        $this->assertNotNull($result->message_id);
    }

    public function test_send_image_message(): void
    {
        Http::fake([
            'localhost:3000/api/sendImage' => Http::response([
                'messages' => [
                    ['id' => 'false_12345678901@c.us_IMAGE123'],
                ],
                'timestamp' => time(),
            ], 201),
        ]);

        $provider = new WahaProvider();
        $dto = WhatsAppTextMessageDto::from([
            'to' => '12345678901',
            'type' => 'image',
            'mediaUrl' => 'http://example.com/image.jpg',
            'caption' => 'Test image',
        ]);

        $result = $provider->sendMessage($dto);

        $this->assertEquals(201, $result->response_code);
        $this->assertNotNull($result->message_id);
    }

    public function test_upload_media_returns_null(): void
    {
        $provider = new WahaProvider();
        $result = $provider->uploadMedia('/path/to/file.jpg', 'image/jpeg');

        $this->assertNull($result);
    }

    public function test_mark_as_read(): void
    {
        Http::fake([
            'localhost:3000/api/sendSeen' => Http::response([], 200),
        ]);

        $provider = new WahaProvider();

        // Should not throw exception
        $provider->markAsRead('false_12345678901@c.us_ABCDEF');

        Http::assertSent(function ($request) {
            return $request->url() === 'http://localhost:3000/api/sendSeen'
                && $request['session'] === 'default'
                && $request['chatId'] === '12345678901@c.us';
        });
    }

    public function test_get_media_url(): void
    {
        $provider = new WahaProvider();
        $result = $provider->getMediaUrl('media_12345.oga');

        $this->assertStringContainsString('/api/files/media_12345.oga', $result);
    }

    public function test_download_media(): void
    {
        Http::fake([
            'localhost:3000/api/files/media_12345.oga' => Http::response(
                'fake audio content',
                200,
                ['Content-Type' => 'audio/ogg']
            ),
        ]);

        $provider = new WahaProvider();
        $result = $provider->downloadMedia('media_12345.oga');

        $this->assertNotNull($result);
        $this->assertFileExists($result);
        $this->assertStringEndsWith('.ogg', $result);

        // Cleanup
        @unlink($result);
        @rmdir(dirname($result));
    }

    public function test_download_media_with_filename(): void
    {
        Http::fake([
            'localhost:3000/api/files/media_12345.pdf' => Http::response(
                'fake pdf content',
                200,
                ['Content-Type' => 'application/pdf']
            ),
        ]);

        $provider = new WahaProvider();
        $result = $provider->downloadMedia('media_12345.pdf', 'document.pdf');

        $this->assertNotNull($result);
        $this->assertFileExists($result);
        $this->assertStringEndsWith('document.pdf', $result);

        // Cleanup
        @unlink($result);
        @rmdir(dirname($result));
    }
}
