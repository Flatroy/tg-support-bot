<?php

namespace Tests\Unit\DTOs\WhatsApp;

use App\DTOs\WhatsApp\WhatsAppUpdateDto;
use Illuminate\Support\Facades\Request;
use Tests\TestCase;

class WhatsAppUpdateDtoTest extends TestCase
{
    public function test_parse_text_message(): void
    {
        $params = $this->getTextMessageParams();
        $request = Request::create('api/whatsapp/bot', 'POST', $params);

        $dto = WhatsAppUpdateDto::fromRequest($request);

        $this->assertNotNull($dto);
        $this->assertEquals('text', $dto->type);
        $this->assertEquals('Hello World', $dto->text);
        $this->assertEquals('15559876543', $dto->from);
        $this->assertNull($dto->mediaId);
    }

    public function test_parse_image_message(): void
    {
        $params = $this->getImageMessageParams();
        $request = Request::create('api/whatsapp/bot', 'POST', $params);

        $dto = WhatsAppUpdateDto::fromRequest($request);

        $this->assertNotNull($dto);
        $this->assertEquals('image', $dto->type);
        $this->assertEquals('media_123', $dto->mediaId);
        $this->assertEquals('image/jpeg', $dto->mimeType);
        $this->assertEquals('Photo caption', $dto->caption);
        $this->assertNull($dto->text);
    }

    public function test_parse_location_message(): void
    {
        $params = $this->getLocationMessageParams();
        $request = Request::create('api/whatsapp/bot', 'POST', $params);

        $dto = WhatsAppUpdateDto::fromRequest($request);

        $this->assertNotNull($dto);
        $this->assertEquals('location', $dto->type);
        $this->assertIsArray($dto->location);
        $this->assertEquals(55.7558, $dto->location['latitude']);
        $this->assertEquals(37.6173, $dto->location['longitude']);
    }

    public function test_parse_status_update(): void
    {
        $params = $this->getStatusParams();
        $request = Request::create('api/whatsapp/bot', 'POST', $params);

        $dto = WhatsAppUpdateDto::fromRequest($request);

        $this->assertNotNull($dto);
        $this->assertEquals('status', $dto->type);
        $this->assertNotNull($dto->status);
    }

    public function test_returns_null_for_empty_request(): void
    {
        $request = Request::create('api/whatsapp/bot', 'POST', []);

        $dto = WhatsAppUpdateDto::fromRequest($request);

        $this->assertNull($dto);
    }

    public function test_returns_null_for_invalid_structure(): void
    {
        $request = Request::create('api/whatsapp/bot', 'POST', ['entry' => []]);

        $dto = WhatsAppUpdateDto::fromRequest($request);

        $this->assertNull($dto);
    }

    public function test_parse_document_message(): void
    {
        $params = $this->getDocumentMessageParams();
        $request = Request::create('api/whatsapp/bot', 'POST', $params);

        $dto = WhatsAppUpdateDto::fromRequest($request);

        $this->assertNotNull($dto);
        $this->assertEquals('document', $dto->type);
        $this->assertEquals('doc_media_123', $dto->mediaId);
        $this->assertEquals('application/pdf', $dto->mimeType);
        $this->assertEquals('test.pdf', $dto->filename);
    }

    public function test_image_message_has_null_filename(): void
    {
        $params = $this->getImageMessageParams();
        $request = Request::create('api/whatsapp/bot', 'POST', $params);

        $dto = WhatsAppUpdateDto::fromRequest($request);

        $this->assertNotNull($dto);
        $this->assertNull($dto->filename);
    }

    public function test_text_message_has_null_filename(): void
    {
        $params = $this->getTextMessageParams();
        $request = Request::create('api/whatsapp/bot', 'POST', $params);

        $dto = WhatsAppUpdateDto::fromRequest($request);

        $this->assertNotNull($dto);
        $this->assertNull($dto->filename);
    }

    public function test_status_update_has_null_filename(): void
    {
        $params = $this->getStatusParams();
        $request = Request::create('api/whatsapp/bot', 'POST', $params);

        $dto = WhatsAppUpdateDto::fromRequest($request);

        $this->assertNotNull($dto);
        $this->assertNull($dto->filename);
    }

    public function test_parse_audio_message(): void
    {
        $params = $this->getAudioMessageParams();
        $request = Request::create('api/whatsapp/bot', 'POST', $params);

        $dto = WhatsAppUpdateDto::fromRequest($request);

        $this->assertNotNull($dto);
        $this->assertEquals('audio', $dto->type);
        $this->assertEquals('audio_media_123', $dto->mediaId);
        $this->assertEquals('audio/ogg', $dto->mimeType);
        $this->assertNull($dto->filename);
    }

    public function test_parse_video_message(): void
    {
        $params = $this->getVideoMessageParams();
        $request = Request::create('api/whatsapp/bot', 'POST', $params);

        $dto = WhatsAppUpdateDto::fromRequest($request);

        $this->assertNotNull($dto);
        $this->assertEquals('video', $dto->type);
        $this->assertEquals('video_media_123', $dto->mediaId);
        $this->assertEquals('video/mp4', $dto->mimeType);
        $this->assertEquals('Test video', $dto->caption);
    }

    public function test_parse_sticker_message(): void
    {
        $params = $this->getStickerMessageParams();
        $request = Request::create('api/whatsapp/bot', 'POST', $params);

        $dto = WhatsAppUpdateDto::fromRequest($request);

        $this->assertNotNull($dto);
        $this->assertEquals('sticker', $dto->type);
        $this->assertEquals('sticker_media_123', $dto->mediaId);
        $this->assertEquals('image/webp', $dto->mimeType);
    }

    private function getTextMessageParams(): array
    {
        return [
            'object' => 'whatsapp_business_account',
            'entry' => [
                [
                    'id' => '123',
                    'changes' => [
                        [
                            'value' => [
                                'messaging_product' => 'whatsapp',
                                'metadata' => ['display_phone_number' => '15551234567', 'phone_number_id' => '123'],
                                'messages' => [
                                    [
                                        'from' => '15559876543',
                                        'id' => 'wamid.test123',
                                        'timestamp' => (string) time(),
                                        'text' => ['body' => 'Hello World'],
                                        'type' => 'text',
                                    ],
                                ],
                            ],
                            'field' => 'messages',
                        ],
                    ],
                ],
            ],
        ];
    }

    private function getImageMessageParams(): array
    {
        return [
            'object' => 'whatsapp_business_account',
            'entry' => [
                [
                    'id' => '123',
                    'changes' => [
                        [
                            'value' => [
                                'messaging_product' => 'whatsapp',
                                'metadata' => ['display_phone_number' => '15551234567', 'phone_number_id' => '123'],
                                'messages' => [
                                    [
                                        'from' => '15559876543',
                                        'id' => 'wamid.test456',
                                        'timestamp' => (string) time(),
                                        'type' => 'image',
                                        'image' => [
                                            'id' => 'media_123',
                                            'mime_type' => 'image/jpeg',
                                            'caption' => 'Photo caption',
                                        ],
                                    ],
                                ],
                            ],
                            'field' => 'messages',
                        ],
                    ],
                ],
            ],
        ];
    }

    private function getLocationMessageParams(): array
    {
        return [
            'object' => 'whatsapp_business_account',
            'entry' => [
                [
                    'id' => '123',
                    'changes' => [
                        [
                            'value' => [
                                'messaging_product' => 'whatsapp',
                                'metadata' => ['display_phone_number' => '15551234567', 'phone_number_id' => '123'],
                                'messages' => [
                                    [
                                        'from' => '15559876543',
                                        'id' => 'wamid.test789',
                                        'timestamp' => (string) time(),
                                        'type' => 'location',
                                        'location' => [
                                            'latitude' => 55.7558,
                                            'longitude' => 37.6173,
                                        ],
                                    ],
                                ],
                            ],
                            'field' => 'messages',
                        ],
                    ],
                ],
            ],
        ];
    }

    private function getDocumentMessageParams(): array
    {
        return [
            'object' => 'whatsapp_business_account',
            'entry' => [
                [
                    'id' => '123',
                    'changes' => [
                        [
                            'value' => [
                                'messaging_product' => 'whatsapp',
                                'metadata' => ['display_phone_number' => '15551234567', 'phone_number_id' => '123'],
                                'messages' => [
                                    [
                                        'from' => '15559876543',
                                        'id' => 'wamid.testdoc',
                                        'timestamp' => (string) time(),
                                        'type' => 'document',
                                        'document' => [
                                            'id' => 'doc_media_123',
                                            'mime_type' => 'application/pdf',
                                            'filename' => 'test.pdf',
                                        ],
                                    ],
                                ],
                            ],
                            'field' => 'messages',
                        ],
                    ],
                ],
            ],
        ];
    }

    private function getAudioMessageParams(): array
    {
        return [
            'object' => 'whatsapp_business_account',
            'entry' => [
                [
                    'id' => '123',
                    'changes' => [
                        [
                            'value' => [
                                'messaging_product' => 'whatsapp',
                                'metadata' => ['display_phone_number' => '15551234567', 'phone_number_id' => '123'],
                                'messages' => [
                                    [
                                        'from' => '15559876543',
                                        'id' => 'wamid.testaudio',
                                        'timestamp' => (string) time(),
                                        'type' => 'audio',
                                        'audio' => [
                                            'id' => 'audio_media_123',
                                            'mime_type' => 'audio/ogg',
                                        ],
                                    ],
                                ],
                            ],
                            'field' => 'messages',
                        ],
                    ],
                ],
            ],
        ];
    }

    private function getVideoMessageParams(): array
    {
        return [
            'object' => 'whatsapp_business_account',
            'entry' => [
                [
                    'id' => '123',
                    'changes' => [
                        [
                            'value' => [
                                'messaging_product' => 'whatsapp',
                                'metadata' => ['display_phone_number' => '15551234567', 'phone_number_id' => '123'],
                                'messages' => [
                                    [
                                        'from' => '15559876543',
                                        'id' => 'wamid.testvideo',
                                        'timestamp' => (string) time(),
                                        'type' => 'video',
                                        'video' => [
                                            'id' => 'video_media_123',
                                            'mime_type' => 'video/mp4',
                                            'caption' => 'Test video',
                                        ],
                                    ],
                                ],
                            ],
                            'field' => 'messages',
                        ],
                    ],
                ],
            ],
        ];
    }

    private function getStickerMessageParams(): array
    {
        return [
            'object' => 'whatsapp_business_account',
            'entry' => [
                [
                    'id' => '123',
                    'changes' => [
                        [
                            'value' => [
                                'messaging_product' => 'whatsapp',
                                'metadata' => ['display_phone_number' => '15551234567', 'phone_number_id' => '123'],
                                'messages' => [
                                    [
                                        'from' => '15559876543',
                                        'id' => 'wamid.teststicker',
                                        'timestamp' => (string) time(),
                                        'type' => 'sticker',
                                        'sticker' => [
                                            'id' => 'sticker_media_123',
                                            'mime_type' => 'image/webp',
                                        ],
                                    ],
                                ],
                            ],
                            'field' => 'messages',
                        ],
                    ],
                ],
            ],
        ];
    }

    private function getStatusParams(): array
    {
        return [
            'object' => 'whatsapp_business_account',
            'entry' => [
                [
                    'id' => '123',
                    'changes' => [
                        [
                            'value' => [
                                'messaging_product' => 'whatsapp',
                                'metadata' => ['display_phone_number' => '15551234567', 'phone_number_id' => '123'],
                                'statuses' => [
                                    [
                                        'id' => 'wamid.status123',
                                        'status' => 'read',
                                        'timestamp' => (string) time(),
                                        'recipient_id' => '15559876543',
                                    ],
                                ],
                            ],
                            'field' => 'messages',
                        ],
                    ],
                ],
            ],
        ];
    }
}
