<?php

namespace Tests\Unit\DTOs\WhatsApp;

use App\DTOs\WhatsApp\WhatsAppAnswerDto;
use Tests\TestCase;

class WhatsAppAnswerDtoTest extends TestCase
{
    public function test_parse_successful_response(): void
    {
        $data = [
            'response_code' => 200,
            'messaging_product' => 'whatsapp',
            'contacts' => [
                ['input' => '15559876543', 'wa_id' => '15559876543'],
            ],
            'messages' => [
                ['id' => 'wamid.abc123'],
            ],
        ];

        $dto = WhatsAppAnswerDto::fromData($data);

        $this->assertEquals(200, $dto->response_code);
        $this->assertEquals('wamid.abc123', $dto->message_id);
        $this->assertNull($dto->error_message);
        $this->assertNull($dto->error_type);
    }

    public function test_parse_error_response(): void
    {
        $data = [
            'response_code' => 400,
            'error' => [
                'message' => 'Invalid parameter',
                'type' => 'OAuthException',
                'code' => 100,
            ],
        ];

        $dto = WhatsAppAnswerDto::fromData($data);

        $this->assertEquals(400, $dto->response_code);
        $this->assertNull($dto->message_id);
        $this->assertEquals('Invalid parameter', $dto->error_message);
        $this->assertEquals('OAuthException', $dto->error_type);
    }

    public function test_parse_response_without_code_defaults_to_200(): void
    {
        $data = [
            'messages' => [
                ['id' => 'wamid.xyz789'],
            ],
        ];

        $dto = WhatsAppAnswerDto::fromData($data);

        $this->assertEquals(200, $dto->response_code);
        $this->assertEquals('wamid.xyz789', $dto->message_id);
    }
}
