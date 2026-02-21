<?php

declare(strict_types=1);

namespace Tests\Unit\DTOs\WhatsApp;

use App\DTOs\WhatsApp\WahaUpdateDto;
use Illuminate\Support\Facades\Request;
use Tests\TestCase;

class WahaUpdateDtoTest extends TestCase
{
    public function test_from_request_with_text_message(): void
    {
        $dto = WahaUpdateDto::fromRequest($this->messageRequest([
            'id' => 'false_12345678901@c.us_ABCDEF',
            'body' => 'Hello from WAHA',
            'hasMedia' => false,
            'type' => 'chat',
        ]));

        $this->assertNotNull($dto);
        $this->assertEquals('false_12345678901@c.us_ABCDEF', $dto->messageId);
        $this->assertEquals('12345678901@c.us', $dto->from);
        $this->assertEquals('text', $dto->type);
        $this->assertEquals('Hello from WAHA', $dto->text);
        $this->assertNull($dto->mediaId);
    }

    public function test_from_request_with_image_message(): void
    {
        $dto = WahaUpdateDto::fromRequest($this->messageRequest([
            'id' => 'false_12345678901@c.us_IMAGE123',
            'body' => 'Check this image',
            'hasMedia' => true,
            'media' => [
                'id' => 'media_12345.jpg',
                'mimetype' => 'image/jpeg',
                'filename' => 'photo.jpg',
            ],
            'type' => 'image',
        ]));

        $this->assertNotNull($dto);
        $this->assertEquals('image', $dto->type);
        $this->assertEquals('media_12345.jpg', $dto->mediaId);
        $this->assertEquals('image/jpeg', $dto->mimeType);
        $this->assertEquals('photo.jpg', $dto->filename);
        $this->assertEquals('Check this image', $dto->caption);
    }

    public function test_from_request_with_location(): void
    {
        $dto = WahaUpdateDto::fromRequest($this->messageRequest([
            'id' => 'false_12345678901@c.us_LOC123',
            'hasMedia' => false,
            'location' => [
                'latitude' => 55.7558,
                'longitude' => 37.6173,
                'description' => 'Moscow',
            ],
        ]));

        $this->assertNotNull($dto);
        $this->assertEquals('location', $dto->type);
        $this->assertNotNull($dto->location);
        $this->assertEquals(55.7558, $dto->location['latitude']);
        $this->assertEquals(37.6173, $dto->location['longitude']);
    }

    public function test_from_request_with_contacts(): void
    {
        $dto = WahaUpdateDto::fromRequest($this->messageRequest([
            'id' => 'false_12345678901@c.us_CONTACT123',
            'hasMedia' => false,
            'vCards' => [
                'BEGIN:VCARD\nVERSION:3.0\nFN:John Doe\nTEL:+1234567890\nEND:VCARD',
            ],
        ]));

        $this->assertNotNull($dto);
        $this->assertEquals('contacts', $dto->type);
        $this->assertNotNull($dto->contacts);
        $this->assertCount(1, $dto->contacts);
    }

    public function test_from_request_with_ack_event(): void
    {
        $request = Request::create('/api/waha/bot', 'POST', [
            'event' => 'message.ack',
            'payload' => [
                'id' => 'false_12345678901@c.us_ABCDEF',
                'to' => '12345678901@c.us',
                'ack' => 3,
                'ackName' => 'READ',
            ],
        ]);

        $dto = WahaUpdateDto::fromRequest($request);

        $this->assertNotNull($dto);
        $this->assertEquals('status', $dto->type);
        $this->assertNotNull($dto->status);
        $this->assertEquals(3, $dto->status['ack']);
        $this->assertEquals('READ', $dto->status['ackName']);
    }

    public function test_from_request_with_raw_format(): void
    {
        $request = Request::create('/api/waha/bot', 'POST', [
            'id' => 'false_12345678901@c.us_RAW123',
            'timestamp' => time(),
            'from' => '12345678901@c.us',
            'to' => 'me@c.us',
            'body' => 'Raw format message',
            'hasMedia' => false,
        ]);

        $dto = WahaUpdateDto::fromRequest($request);

        $this->assertNotNull($dto);
        $this->assertEquals('false_12345678901@c.us_RAW123', $dto->messageId);
        $this->assertEquals('text', $dto->type);
    }

    public function test_from_request_returns_null_for_invalid_data(): void
    {
        $request = Request::create('/api/waha/bot', 'POST', ['some_random_field' => 'value']);
        $dto = WahaUpdateDto::fromRequest($request);

        $this->assertNull($dto);
    }

    public function test_audio_message_type(): void
    {
        $dto = WahaUpdateDto::fromRequest($this->messageRequest([
            'id' => 'false_12345678901@c.us_AUDIO123',
            'hasMedia' => true,
            'media' => [
                'id' => 'media_voice.oga',
                'mimetype' => 'audio/ogg; codecs=opus',
            ],
        ]));

        $this->assertNotNull($dto);
        $this->assertEquals('audio', $dto->type);
    }

    public function test_video_message_type(): void
    {
        $dto = WahaUpdateDto::fromRequest($this->messageRequest([
            'id' => 'false_12345678901@c.us_VIDEO123',
            'hasMedia' => true,
            'media' => [
                'id' => 'media_video.mp4',
                'mimetype' => 'video/mp4',
            ],
        ]));

        $this->assertNotNull($dto);
        $this->assertEquals('video', $dto->type);
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function messageRequest(array $payload): \Illuminate\Http\Request
    {
        return Request::create('/api/waha/bot', 'POST', [
            'event' => 'message',
            'payload' => array_merge([
                'id' => 'false_12345678901@c.us_TEST001',
                'timestamp' => time(),
                'from' => '12345678901@c.us',
                'to' => 'me@c.us',
                'body' => null,
                'hasMedia' => false,
            ], $payload),
        ]);
    }
}
