<?php

declare(strict_types=1);

namespace Tests\Unit\WhatsApp;

use App\WhatsAppBot\WhatsAppMethods;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class WhatsAppMethodsTest extends TestCase
{
    public function setUp(): void
    {
        parent::setUp();

        config([
            'traffic_source.settings.whatsapp.token' => 'test_token',
            'traffic_source.settings.whatsapp.phone_number_id' => '123456789',
            'traffic_source.settings.whatsapp.api_version' => 'v22.0',
        ]);
    }

    public function test_upload_media_returns_media_id(): void
    {
        Http::fake([
            'graph.facebook.com/v22.0/123456789/media' => Http::response(['id' => 'media_id_abc'], 200),
        ]);

        $tempFile = tempnam(sys_get_temp_dir(), 'test_');
        file_put_contents($tempFile, 'fake image content');

        $result = WhatsAppMethods::uploadMedia($tempFile, 'image/jpeg');

        @unlink($tempFile);

        $this->assertEquals('media_id_abc', $result);

        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'graph.facebook.com/v22.0/123456789/media')
                && $request->hasHeader('Authorization', 'Bearer test_token');
        });
    }

    public function test_upload_media_returns_null_on_failure(): void
    {
        Http::fake([
            'graph.facebook.com/*' => Http::response(['error' => ['message' => 'fail']], 400),
        ]);

        $tempFile = tempnam(sys_get_temp_dir(), 'test_');
        file_put_contents($tempFile, 'fake content');

        $result = WhatsAppMethods::uploadMedia($tempFile, 'image/jpeg');

        @unlink($tempFile);

        $this->assertNull($result);
    }

    public function test_get_media_url_returns_url(): void
    {
        Http::fake([
            'graph.facebook.com/v22.0/media_123' => Http::response(['url' => 'https://example.com/media.jpg'], 200),
        ]);

        $result = WhatsAppMethods::getMediaUrl('media_123');

        $this->assertEquals('https://example.com/media.jpg', $result);
    }

    public function test_get_media_url_returns_null_on_failure(): void
    {
        Http::fake([
            'graph.facebook.com/*' => Http::response(['error' => 'not found'], 404),
        ]);

        $result = WhatsAppMethods::getMediaUrl('invalid_id');

        $this->assertNull($result);
    }

    public function test_download_media_saves_file_with_content_type_extension(): void
    {
        Http::fake([
            'https://media.example.com/file' => Http::response('fake image bytes', 200, [
                'Content-Type' => 'image/jpeg',
            ]),
        ]);

        $result = WhatsAppMethods::downloadMedia('https://media.example.com/file');

        $this->assertNotNull($result);
        $this->assertFileExists($result);
        $this->assertStringEndsWith('.jpg', $result);
        $this->assertEquals('fake image bytes', file_get_contents($result));

        @unlink($result);
    }

    public function test_download_media_saves_file_with_original_filename(): void
    {
        Http::fake([
            'https://media.example.com/file' => Http::response('file content', 200),
        ]);

        $result = WhatsAppMethods::downloadMedia('https://media.example.com/file', 'Ireland.ovpn');

        $this->assertNotNull($result);
        $this->assertFileExists($result);
        $this->assertEquals('Ireland.ovpn', basename($result));
        $this->assertEquals('file content', file_get_contents($result));

        @unlink($result);
        @rmdir(dirname($result));
    }

    public function test_download_media_returns_null_on_failure(): void
    {
        Http::fake([
            'https://media.example.com/file' => Http::response('', 500),
        ]);

        $result = WhatsAppMethods::downloadMedia('https://media.example.com/file');

        $this->assertNull($result);
    }

    public function test_download_media_png_extension(): void
    {
        Http::fake([
            'https://media.example.com/file' => Http::response('png bytes', 200, [
                'Content-Type' => 'image/png',
            ]),
        ]);

        $result = WhatsAppMethods::downloadMedia('https://media.example.com/file');

        $this->assertNotNull($result);
        $this->assertStringEndsWith('.png', $result);

        @unlink($result);
    }

    public function test_download_media_ogg_extension(): void
    {
        Http::fake([
            'https://media.example.com/file' => Http::response('ogg bytes', 200, [
                'Content-Type' => 'audio/ogg',
            ]),
        ]);

        $result = WhatsAppMethods::downloadMedia('https://media.example.com/file');

        $this->assertNotNull($result);
        $this->assertStringEndsWith('.ogg', $result);

        @unlink($result);
    }

    public function test_download_media_unknown_type_uses_bin_extension(): void
    {
        Http::fake([
            'https://media.example.com/file' => Http::response('binary bytes', 200, [
                'Content-Type' => 'application/octet-stream',
            ]),
        ]);

        $result = WhatsAppMethods::downloadMedia('https://media.example.com/file');

        $this->assertNotNull($result);
        $this->assertStringEndsWith('.bin', $result);

        @unlink($result);
    }

    public function test_send_message_text(): void
    {
        Http::fake([
            'graph.facebook.com/*' => Http::response([
                'messaging_product' => 'whatsapp',
                'messages' => [['id' => 'wamid.test123']],
            ], 200),
        ]);

        $dto = \App\DTOs\WhatsApp\WhatsAppTextMessageDto::from([
            'to' => '15559876543',
            'type' => 'text',
            'text' => 'Hello',
        ]);

        $result = WhatsAppMethods::sendMessage($dto);

        $this->assertEquals('wamid.test123', $result->message_id);
        $this->assertEquals(200, $result->response_code);
    }

    public function test_send_message_with_media_id(): void
    {
        Http::fake([
            'graph.facebook.com/*' => Http::response([
                'messaging_product' => 'whatsapp',
                'messages' => [['id' => 'wamid.media456']],
            ], 200),
        ]);

        $dto = \App\DTOs\WhatsApp\WhatsAppTextMessageDto::from([
            'to' => '15559876543',
            'type' => 'image',
            'mediaId' => 'uploaded_media_id',
            'caption' => 'Test photo',
        ]);

        $result = WhatsAppMethods::sendMessage($dto);

        $this->assertEquals('wamid.media456', $result->message_id);

        Http::assertSent(function ($request) {
            $body = $request->data();

            return $body['type'] === 'image'
                && $body['image']['id'] === 'uploaded_media_id'
                && $body['image']['caption'] === 'Test photo';
        });
    }

    public function test_send_message_document_with_filename(): void
    {
        Http::fake([
            'graph.facebook.com/*' => Http::response([
                'messaging_product' => 'whatsapp',
                'messages' => [['id' => 'wamid.doc789']],
            ], 200),
        ]);

        $dto = \App\DTOs\WhatsApp\WhatsAppTextMessageDto::from([
            'to' => '15559876543',
            'type' => 'document',
            'mediaUrl' => 'https://example.com/file.pdf',
            'filename' => 'report.pdf',
            'caption' => 'Monthly report',
        ]);

        $result = WhatsAppMethods::sendMessage($dto);

        $this->assertEquals('wamid.doc789', $result->message_id);

        Http::assertSent(function ($request) {
            $body = $request->data();

            return $body['type'] === 'document'
                && $body['document']['filename'] === 'report.pdf'
                && $body['document']['link'] === 'https://example.com/file.pdf';
        });
    }

    public function test_send_message_returns_error_on_failure(): void
    {
        Http::fake([
            'graph.facebook.com/*' => Http::response([
                'error' => ['message' => 'Invalid token', 'type' => 'OAuthException'],
            ], 401),
        ]);

        $dto = \App\DTOs\WhatsApp\WhatsAppTextMessageDto::from([
            'to' => '15559876543',
            'type' => 'text',
            'text' => 'Hello',
        ]);

        $result = WhatsAppMethods::sendMessage($dto);

        $this->assertEquals(401, $result->response_code);
        $this->assertNotEmpty($result->error_message);
    }

    public function test_mark_as_read_sends_correct_payload(): void
    {
        Http::fake([
            'graph.facebook.com/*' => Http::response(['success' => true], 200),
        ]);

        WhatsAppMethods::markAsRead('wamid.test123');

        Http::assertSent(function ($request) {
            $body = $request->data();

            return $body['messaging_product'] === 'whatsapp'
                && $body['status'] === 'read'
                && $body['message_id'] === 'wamid.test123';
        });
    }
}
