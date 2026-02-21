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

        $result = (new WahaProvider())->sendMessage(WhatsAppTextMessageDto::from([
            'to' => '12345678901',
            'type' => 'text',
            'text' => 'Test message',
        ]));

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

        $result = (new WahaProvider())->sendMessage(WhatsAppTextMessageDto::from([
            'to' => '12345678901',
            'type' => 'image',
            'mediaUrl' => 'http://example.com/image.jpg',
            'caption' => 'Test image',
        ]));

        $this->assertEquals(201, $result->response_code);
        $this->assertNotNull($result->message_id);
    }

    public function test_upload_media_returns_null(): void
    {
        $this->assertNull((new WahaProvider())->uploadMedia('/path/to/file.jpg', 'image/jpeg'));
    }

    public function test_mark_as_read(): void
    {
        Http::fake([
            'localhost:3000/api/sendSeen' => Http::response([], 200),
        ]);

        (new WahaProvider())->markAsRead('false_12345678901@c.us_ABCDEF');

        Http::assertSent(function ($request): bool {
            return $request->url() === 'http://localhost:3000/api/sendSeen'
                && $request['session'] === 'default'
                && $request['chatId'] === '12345678901@c.us';
        });
    }

    public function test_get_media_url(): void
    {
        $result = (new WahaProvider())->getMediaUrl('media_12345.oga');

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

        $result = (new WahaProvider())->downloadMedia('media_12345.oga');

        $this->assertNotNull($result);
        $this->assertFileExists($result);
        $this->assertStringEndsWith('.ogg', $result);

        $this->cleanupFile($result);
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

        $result = (new WahaProvider())->downloadMedia('media_12345.pdf', 'document.pdf');

        $this->assertNotNull($result);
        $this->assertFileExists($result);
        $this->assertStringEndsWith('document.pdf', $result);

        $this->cleanupFile($result);
    }

    public function test_download_media_with_full_url(): void
    {
        Http::fake([
            'http://localhost:3000/api/files/media_12345.oga' => Http::response(
                'fake audio content',
                200,
                ['Content-Type' => 'audio/ogg']
            ),
        ]);

        $fullUrl = 'http://localhost:3000/api/files/media_12345.oga';
        $result = (new WahaProvider())->downloadMedia($fullUrl);

        $this->assertNotNull($result);
        $this->assertFileExists($result);
        $this->assertStringEndsWith('.ogg', $result);

        $this->cleanupFile($result);
    }

    private function cleanupFile(string $path): void
    {
        @unlink($path);
        @rmdir(dirname($path));
    }
}
