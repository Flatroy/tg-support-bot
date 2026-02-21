<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Controllers;

use App\Models\BotUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class WahaBotControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();

        BotUser::create([
            'chat_id' => '12345678901',
            'platform' => 'whatsapp',
            'username' => 'testuser',
            'first_name' => 'Test',
            'last_name' => 'User',
        ]);
    }

    public function test_text_message_webhook(): void
    {
        $response = $this->postJson('/api/waha/bot', $this->messageEventPayload([
            'id' => 'false_12345678901@c.us_TESTMSG001',
            'body' => 'Hello from WAHA test',
            'hasMedia' => false,
            'type' => 'chat',
        ]));

        $response->assertOk()->assertContent('ok');
    }

    public function test_image_message_webhook(): void
    {
        $response = $this->postJson('/api/waha/bot', $this->messageEventPayload([
            'id' => 'false_12345678901@c.us_TESTIMG001',
            'body' => 'Test image caption',
            'hasMedia' => true,
            'media' => [
                'id' => 'media_test123.jpg',
                'mimetype' => 'image/jpeg',
                'filename' => 'test.jpg',
            ],
            'type' => 'image',
        ]));

        $response->assertOk()->assertContent('ok');
    }

    public function test_ack_status_webhook(): void
    {
        $response = $this->postJson('/api/waha/bot', [
            'event' => 'message.ack',
            'payload' => [
                'id' => 'false_12345678901@c.us_TESTMSG001',
                'to' => '12345678901@c.us',
                'ack' => 3,
                'ackName' => 'READ',
            ],
        ]);

        $response->assertOk()->assertContent('ok');
    }

    public function test_deduplication(): void
    {
        $payload = $this->messageEventPayload([
            'id' => 'false_12345678901@c.us_DUPLICATE001',
            'body' => 'Duplicate message',
            'hasMedia' => false,
            'type' => 'chat',
        ]);

        $this->postJson('/api/waha/bot', $payload)->assertOk();
        $this->postJson('/api/waha/bot', $payload)->assertOk();
    }

    public function test_location_message_webhook(): void
    {
        $response = $this->postJson('/api/waha/bot', $this->messageEventPayload([
            'id' => 'false_12345678901@c.us_TESTLOC001',
            'hasMedia' => false,
            'location' => [
                'latitude' => 55.7558,
                'longitude' => 37.6173,
                'description' => 'Moscow',
            ],
        ]));

        $response->assertOk()->assertContent('ok');
    }

    public function test_document_message_webhook(): void
    {
        $response = $this->postJson('/api/waha/bot', $this->messageEventPayload([
            'id' => 'false_12345678901@c.us_TESTDOC001',
            'body' => 'Document caption',
            'hasMedia' => true,
            'media' => [
                'id' => 'media_doc123.pdf',
                'mimetype' => 'application/pdf',
                'filename' => 'document.pdf',
            ],
            'type' => 'document',
        ]));

        $response->assertOk()->assertContent('ok');
    }

    public function test_invalid_payload_returns_ok(): void
    {
        $this->postJson('/api/waha/bot', ['invalid_field' => 'value'])
            ->assertOk()
            ->assertContent('ok');
    }

    public function test_contacts_message_webhook(): void
    {
        $response = $this->postJson('/api/waha/bot', $this->messageEventPayload([
            'id' => 'false_12345678901@c.us_TESTCONTACT001',
            'hasMedia' => false,
            'vCards' => [
                "BEGIN:VCARD\nVERSION:3.0\nFN:John Doe\nTEL:+1234567890\nEND:VCARD",
            ],
        ]));

        $response->assertOk()->assertContent('ok');
    }

    /**
     * @param array<string, mixed> $payload
     *
     * @return array<string, mixed>
     */
    private function messageEventPayload(array $payload): array
    {
        return [
            'event' => 'message',
            'payload' => array_merge([
                'id' => 'false_12345678901@c.us_TEST001',
                'timestamp' => time(),
                'from' => '12345678901@c.us',
                'to' => 'me@c.us',
                'hasMedia' => false,
            ], $payload),
        ];
    }
}
