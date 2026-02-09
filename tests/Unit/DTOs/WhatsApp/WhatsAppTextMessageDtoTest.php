<?php

declare(strict_types=1);

namespace Tests\Unit\DTOs\WhatsApp;

use App\DTOs\WhatsApp\WhatsAppTextMessageDto;
use Tests\TestCase;

class WhatsAppTextMessageDtoTest extends TestCase
{
    public function test_text_payload(): void
    {
        $dto = WhatsAppTextMessageDto::from([
            'to' => '15559876543',
            'type' => 'text',
            'text' => 'Hello World',
        ]);

        $payload = $dto->toApiPayload();

        $this->assertEquals('whatsapp', $payload['messaging_product']);
        $this->assertEquals('individual', $payload['recipient_type']);
        $this->assertEquals('15559876543', $payload['to']);
        $this->assertEquals('text', $payload['type']);
        $this->assertEquals('Hello World', $payload['text']['body']);
    }

    public function test_image_payload_with_url(): void
    {
        $dto = WhatsAppTextMessageDto::from([
            'to' => '15559876543',
            'type' => 'image',
            'mediaUrl' => 'https://example.com/photo.jpg',
            'caption' => 'A nice photo',
        ]);

        $payload = $dto->toApiPayload();

        $this->assertEquals('image', $payload['type']);
        $this->assertEquals('https://example.com/photo.jpg', $payload['image']['link']);
        $this->assertEquals('A nice photo', $payload['image']['caption']);
        $this->assertArrayNotHasKey('id', $payload['image']);
    }

    public function test_image_payload_with_media_id(): void
    {
        $dto = WhatsAppTextMessageDto::from([
            'to' => '15559876543',
            'type' => 'image',
            'mediaId' => 'uploaded_media_123',
            'caption' => 'Uploaded photo',
        ]);

        $payload = $dto->toApiPayload();

        $this->assertEquals('image', $payload['type']);
        $this->assertEquals('uploaded_media_123', $payload['image']['id']);
        $this->assertEquals('Uploaded photo', $payload['image']['caption']);
        $this->assertArrayNotHasKey('link', $payload['image']);
    }

    public function test_document_payload_with_filename(): void
    {
        $dto = WhatsAppTextMessageDto::from([
            'to' => '15559876543',
            'type' => 'document',
            'mediaUrl' => 'https://example.com/file.pdf',
            'filename' => 'report.pdf',
            'caption' => 'Monthly report',
        ]);

        $payload = $dto->toApiPayload();

        $this->assertEquals('document', $payload['type']);
        $this->assertEquals('https://example.com/file.pdf', $payload['document']['link']);
        $this->assertEquals('report.pdf', $payload['document']['filename']);
        $this->assertEquals('Monthly report', $payload['document']['caption']);
    }

    public function test_document_payload_without_filename_excludes_key(): void
    {
        $dto = WhatsAppTextMessageDto::from([
            'to' => '15559876543',
            'type' => 'document',
            'mediaUrl' => 'https://example.com/file.bin',
        ]);

        $payload = $dto->toApiPayload();

        $this->assertEquals('document', $payload['type']);
        $this->assertArrayNotHasKey('filename', $payload['document']);
    }

    public function test_audio_payload(): void
    {
        $dto = WhatsAppTextMessageDto::from([
            'to' => '15559876543',
            'type' => 'audio',
            'mediaId' => 'audio_media_id',
        ]);

        $payload = $dto->toApiPayload();

        $this->assertEquals('audio', $payload['type']);
        $this->assertEquals('audio_media_id', $payload['audio']['id']);
    }

    public function test_video_payload(): void
    {
        $dto = WhatsAppTextMessageDto::from([
            'to' => '15559876543',
            'type' => 'video',
            'mediaUrl' => 'https://example.com/video.mp4',
            'caption' => 'Cool video',
        ]);

        $payload = $dto->toApiPayload();

        $this->assertEquals('video', $payload['type']);
        $this->assertEquals('https://example.com/video.mp4', $payload['video']['link']);
        $this->assertEquals('Cool video', $payload['video']['caption']);
    }

    public function test_location_payload(): void
    {
        $dto = WhatsAppTextMessageDto::from([
            'to' => '15559876543',
            'type' => 'location',
            'latitude' => 55.7558,
            'longitude' => 37.6173,
        ]);

        $payload = $dto->toApiPayload();

        $this->assertEquals('location', $payload['type']);
        $this->assertEquals(55.7558, $payload['location']['latitude']);
        $this->assertEquals(37.6173, $payload['location']['longitude']);
    }

    public function test_template_payload(): void
    {
        $dto = WhatsAppTextMessageDto::from([
            'to' => '15559876543',
            'type' => 'template',
            'templateName' => 'hello_world',
            'templateLanguage' => 'en',
        ]);

        $payload = $dto->toApiPayload();

        $this->assertEquals('template', $payload['type']);
        $this->assertEquals('hello_world', $payload['template']['name']);
        $this->assertEquals('en', $payload['template']['language']['code']);
    }

    public function test_unknown_type_returns_base_payload(): void
    {
        $dto = WhatsAppTextMessageDto::from([
            'to' => '15559876543',
            'type' => 'unknown_type',
        ]);

        $payload = $dto->toApiPayload();

        $this->assertEquals('unknown_type', $payload['type']);
        $this->assertCount(4, $payload);
    }
}
