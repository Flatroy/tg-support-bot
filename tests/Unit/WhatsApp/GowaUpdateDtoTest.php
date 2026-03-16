<?php

declare(strict_types=1);

namespace Tests\Unit\WhatsApp;

use App\DTOs\WhatsApp\GowaUpdateDto;
use Illuminate\Http\Request;
use Tests\TestCase;

class GowaUpdateDtoTest extends TestCase
{
    public function test_parses_text_message(): void
    {
        $request = Request::create('/', 'POST', [
            'event' => 'message',
            'device_id' => '628987654321@s.whatsapp.net',
            'payload' => [
                'id' => 'MSG001',
                'chat_id' => '628987654321@s.whatsapp.net',
                'from' => '628123456789@s.whatsapp.net',
                'from_name' => 'John Doe',
                'timestamp' => '2023-10-15T10:30:00Z',
                'is_from_me' => false,
                'body' => 'Hello, how are you?',
            ],
        ]);

        $dto = GowaUpdateDto::fromRequest($request);

        $this->assertNotNull($dto);
        $this->assertSame('MSG001', $dto->messageId);
        $this->assertSame('628123456789@s.whatsapp.net', $dto->from);
        $this->assertSame('628987654321@s.whatsapp.net', $dto->chatId);
        $this->assertSame('text', $dto->type);
        $this->assertSame('Hello, how are you?', $dto->text);
        $this->assertNull($dto->mediaId);
    }

    public function test_ignores_messages_from_me(): void
    {
        $request = Request::create('/', 'POST', [
            'event' => 'message',
            'device_id' => '628987654321@s.whatsapp.net',
            'payload' => [
                'id' => 'MSG002',
                'chat_id' => '628987654321@s.whatsapp.net',
                'from' => '628987654321@s.whatsapp.net',
                'is_from_me' => true,
                'body' => 'My own message',
            ],
        ]);

        $dto = GowaUpdateDto::fromRequest($request);

        $this->assertNull($dto);
    }

    public function test_parses_image_message_with_path(): void
    {
        $request = Request::create('/', 'POST', [
            'event' => 'message',
            'device_id' => '628987654321@s.whatsapp.net',
            'payload' => [
                'id' => 'IMG001',
                'chat_id' => '628987654321@s.whatsapp.net',
                'from' => '628123456789@s.whatsapp.net',
                'is_from_me' => false,
                'image' => 'statics/media/1752404751-ad9e37ac.jpeg',
                'body' => 'Check this out!',
            ],
        ]);

        $dto = GowaUpdateDto::fromRequest($request);

        $this->assertNotNull($dto);
        $this->assertSame('image', $dto->type);
        $this->assertSame('statics/media/1752404751-ad9e37ac.jpeg', $dto->mediaId);
        $this->assertSame('image/jpeg', $dto->mimeType);
        $this->assertSame('Check this out!', $dto->caption);
    }

    public function test_parses_image_message_with_url_object(): void
    {
        $request = Request::create('/', 'POST', [
            'event' => 'message',
            'device_id' => '628987654321@s.whatsapp.net',
            'payload' => [
                'id' => 'IMG002',
                'chat_id' => '628987654321@s.whatsapp.net',
                'from' => '628123456789@s.whatsapp.net',
                'is_from_me' => false,
                'image' => [
                    'url' => 'https://mmg.whatsapp.net/path/to/image.jpg',
                    'caption' => 'Caption from object',
                ],
            ],
        ]);

        $dto = GowaUpdateDto::fromRequest($request);

        $this->assertNotNull($dto);
        $this->assertSame('image', $dto->type);
        $this->assertSame('https://mmg.whatsapp.net/path/to/image.jpg', $dto->mediaId);
        $this->assertSame('Caption from object', $dto->caption);
    }

    public function test_parses_audio_message(): void
    {
        $request = Request::create('/', 'POST', [
            'event' => 'message',
            'device_id' => '628987654321@s.whatsapp.net',
            'payload' => [
                'id' => 'AUD001',
                'chat_id' => '628987654321@s.whatsapp.net',
                'from' => '628123456789@s.whatsapp.net',
                'is_from_me' => false,
                'audio' => 'statics/media/1752404905-voice.ogg',
            ],
        ]);

        $dto = GowaUpdateDto::fromRequest($request);

        $this->assertNotNull($dto);
        $this->assertSame('audio', $dto->type);
        $this->assertSame('statics/media/1752404905-voice.ogg', $dto->mediaId);
        $this->assertSame('audio/ogg', $dto->mimeType);
    }

    public function test_parses_document_message(): void
    {
        $request = Request::create('/', 'POST', [
            'event' => 'message',
            'device_id' => '628987654321@s.whatsapp.net',
            'payload' => [
                'id' => 'DOC001',
                'chat_id' => '628987654321@s.whatsapp.net',
                'from' => '628123456789@s.whatsapp.net',
                'is_from_me' => false,
                'document' => [
                    'url' => 'https://mmg.whatsapp.net/path/to/report.pdf',
                    'filename' => 'report.pdf',
                ],
            ],
        ]);

        $dto = GowaUpdateDto::fromRequest($request);

        $this->assertNotNull($dto);
        $this->assertSame('document', $dto->type);
        $this->assertSame('https://mmg.whatsapp.net/path/to/report.pdf', $dto->mediaId);
        $this->assertSame('report.pdf', $dto->filename);
        $this->assertSame('application/pdf', $dto->mimeType);
    }

    public function test_parses_location_message(): void
    {
        $request = Request::create('/', 'POST', [
            'event' => 'message',
            'device_id' => '628987654321@s.whatsapp.net',
            'payload' => [
                'id' => 'LOC001',
                'chat_id' => '628987654321@s.whatsapp.net',
                'from' => '628123456789@s.whatsapp.net',
                'is_from_me' => false,
                'location' => [
                    'degreesLatitude' => -6.2088,
                    'degreesLongitude' => 106.8456,
                    'name' => 'Jakarta',
                ],
            ],
        ]);

        $dto = GowaUpdateDto::fromRequest($request);

        $this->assertNotNull($dto);
        $this->assertSame('location', $dto->type);
        $this->assertNotNull($dto->location);
        $this->assertSame(-6.2088, $dto->location['degreesLatitude']);
    }

    public function test_parses_reaction_event(): void
    {
        $request = Request::create('/', 'POST', [
            'event' => 'message.reaction',
            'device_id' => '628987654321@s.whatsapp.net',
            'payload' => [
                'id' => 'REACT001',
                'chat_id' => '628987654321@s.whatsapp.net',
                'from' => '628123456789@s.whatsapp.net',
                'is_from_me' => false,
                'reaction' => '👍',
                'reacted_message_id' => 'MSG001',
            ],
        ]);

        $dto = GowaUpdateDto::fromRequest($request);

        $this->assertNotNull($dto);
        $this->assertSame('reaction', $dto->type);
        $this->assertNotNull($dto->reaction);
        $this->assertSame('👍', $dto->reaction['emoji']);
        $this->assertSame('MSG001', $dto->reaction['messageId']);
    }

    public function test_returns_null_for_unknown_event(): void
    {
        $request = Request::create('/', 'POST', [
            'event' => 'message.ack',
            'payload' => ['id' => 'ACK001'],
        ]);

        $dto = GowaUpdateDto::fromRequest($request);

        $this->assertNull($dto);
    }

    public function test_returns_null_for_empty_payload(): void
    {
        $request = Request::create('/', 'POST', [
            'event' => 'message',
            'payload' => [],
        ]);

        $dto = GowaUpdateDto::fromRequest($request);

        $this->assertNull($dto);
    }

    public function test_parses_ptt_voice_note_as_audio_type(): void
    {
        $request = Request::create('/', 'POST', [
            'event' => 'message',
            'device_id' => '628987654321@s.whatsapp.net',
            'payload' => [
                'id' => 'PTT001',
                'chat_id' => '628987654321@s.whatsapp.net',
                'from' => '628123456789@s.whatsapp.net',
                'is_from_me' => false,
                'ptt' => 'statics/media/1752404905-voice.ogg',
            ],
        ]);

        $dto = GowaUpdateDto::fromRequest($request);

        $this->assertNotNull($dto);
        $this->assertSame('audio', $dto->type);
        $this->assertSame('statics/media/1752404905-voice.ogg', $dto->mediaId);
        $this->assertSame('audio/ogg', $dto->mimeType);
    }

    public function test_parses_sticker_as_image_type(): void
    {
        $request = Request::create('/', 'POST', [
            'event' => 'message',
            'device_id' => '628987654321@s.whatsapp.net',
            'payload' => [
                'id' => 'STICKER001',
                'chat_id' => '628987654321@s.whatsapp.net',
                'from' => '628123456789@s.whatsapp.net',
                'is_from_me' => false,
                'sticker' => 'statics/media/1752404986-sticker.webp',
            ],
        ]);

        $dto = GowaUpdateDto::fromRequest($request);

        $this->assertNotNull($dto);
        $this->assertSame('image', $dto->type);
        $this->assertSame('image/webp', $dto->mimeType);
    }
}
