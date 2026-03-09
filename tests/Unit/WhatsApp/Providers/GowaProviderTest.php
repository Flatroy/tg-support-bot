<?php

declare(strict_types=1);

namespace Tests\Unit\WhatsApp\Providers;

use App\DTOs\WhatsApp\WhatsAppTextMessageDto;
use App\Services\WhatsApp\Providers\GowaProvider;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GowaProviderTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'traffic_source.settings.whatsapp.provider' => 'gowa',
            'traffic_source.settings.whatsapp.gowa.base_url' => 'http://localhost:3000',
            'traffic_source.settings.whatsapp.gowa.device_id' => '',
            'traffic_source.settings.whatsapp.gowa.basic_auth' => '',
        ]);
    }

    public function test_send_text_message(): void
    {
        Http::fake([
            'http://localhost:3000/send/message' => Http::response([
                'status' => 200,
                'code' => 'SUCCESS',
                'results' => ['message_id' => 'test-id-123'],
            ], 200),
        ]);

        $dto = WhatsAppTextMessageDto::from([
            'to' => '628123456789',
            'type' => 'text',
            'text' => 'Hello from GOWA test',
        ]);

        $result = (new GowaProvider())->sendMessage($dto);

        $this->assertEquals(200, $result->response_code);
        Http::assertSent(fn ($request) => str_contains($request->url(), '/send/message'));
    }

    public function test_send_image_message_with_url(): void
    {
        Http::fake([
            'http://localhost:3000/send/image' => Http::response([
                'status' => 200,
                'code' => 'SUCCESS',
                'results' => ['message_id' => 'img-id-456'],
            ], 200),
        ]);

        $dto = WhatsAppTextMessageDto::from([
            'to' => '628123456789',
            'type' => 'image',
            'mediaUrl' => 'https://example.com/image.jpg',
            'caption' => 'Test caption',
        ]);

        $result = (new GowaProvider())->sendMessage($dto);

        $this->assertEquals(200, $result->response_code);
        Http::assertSent(fn ($request) => str_contains($request->url(), '/send/image'));
    }

    public function test_upload_media_returns_filepath_for_existing_file(): void
    {
        $tempFile = sys_get_temp_dir() . '/gowa_test_' . uniqid() . '.jpg';
        file_put_contents($tempFile, 'fake image content');

        $result = (new GowaProvider())->uploadMedia($tempFile, 'image/jpeg');

        $this->assertSame($tempFile, $result);

        unlink($tempFile);
    }

    public function test_upload_media_returns_null_for_nonexistent_file(): void
    {
        $result = (new GowaProvider())->uploadMedia('/nonexistent/file.jpg', 'image/jpeg');

        $this->assertNull($result);
    }

    public function test_download_media_with_full_url(): void
    {
        Http::fake([
            'http://localhost:3000/statics/media/test.jpeg' => Http::response(
                'fake image content',
                200,
                ['Content-Type' => 'image/jpeg']
            ),
        ]);

        $result = (new GowaProvider())->downloadMedia('http://localhost:3000/statics/media/test.jpeg');

        $this->assertNotNull($result);
        $this->assertFileExists($result);
        $this->assertStringEndsWith('.jpg', $result);

        unlink($result);
        rmdir(dirname($result));
    }

    public function test_download_media_with_relative_path(): void
    {
        Http::fake([
            'http://localhost:3000/statics/media/test.jpeg' => Http::response(
                'fake image content',
                200,
                ['Content-Type' => 'image/jpeg']
            ),
        ]);

        $result = (new GowaProvider())->downloadMedia('statics/media/test.jpeg');

        $this->assertNotNull($result);
        $this->assertFileExists($result);

        unlink($result);
        rmdir(dirname($result));
    }

    public function test_download_media_returns_null_on_failure(): void
    {
        Http::fake([
            'http://localhost:3000/*' => Http::response('Not Found', 404),
        ]);

        $result = (new GowaProvider())->downloadMedia('statics/media/missing.jpg');

        $this->assertNull($result);
    }

    public function test_get_media_url_with_relative_path(): void
    {
        $result = (new GowaProvider())->getMediaUrl('statics/media/test.jpeg');

        $this->assertSame('http://localhost:3000/statics/media/test.jpeg', $result);
    }

    public function test_get_media_url_with_full_url(): void
    {
        $url = 'https://mmg.whatsapp.net/some/media/path.jpg';
        $result = (new GowaProvider())->getMediaUrl($url);

        $this->assertSame($url, $result);
    }
}
