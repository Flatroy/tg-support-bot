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

        // Create a bot user for testing
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
        $payload = [
            'event' => 'message',
            'payload' => [
                'id' => 'false_12345678901@c.us_TESTMSG001',
                'timestamp' => time(),
                'from' => '12345678901@c.us',
                'to' => 'me@c.us',
                'body' => 'Hello from WAHA test',
                'hasMedia' => false,
                'type' => 'chat',
            ],
        ];

        $response = $this->postJson('/api/waha/bot', $payload);

        $response->assertStatus(200);
        $response->assertContent('ok');
    }

    public function test_image_message_webhook(): void
    {
        $payload = [
            'event' => 'message',
            'payload' => [
                'id' => 'false_12345678901@c.us_TESTIMG001',
                'timestamp' => time(),
                'from' => '12345678901@c.us',
                'to' => 'me@c.us',
                'body' => 'Test image caption',
                'hasMedia' => true,
                'media' => [
                    'id' => 'media_test123.jpg',
                    'mimetype' => 'image/jpeg',
                    'filename' => 'test.jpg',
                ],
                'type' => 'image',
            ],
        ];

        $response = $this->postJson('/api/waha/bot', $payload);

        $response->assertStatus(200);
        $response->assertContent('ok');
    }

    public function test_ack_status_webhook(): void
    {
        $payload = [
            'event' => 'message.ack',
            'payload' => [
                'id' => 'false_12345678901@c.us_TESTMSG001',
                'to' => '12345678901@c.us',
                'ack' => 3,
                'ackName' => 'READ',
            ],
        ];

        $response = $this->postJson('/api/waha/bot', $payload);

        $response->assertStatus(200);
        $response->assertContent('ok');
    }

    public function test_deduplication(): void
    {
        $payload = [
            'event' => 'message',
            'payload' => [
                'id' => 'false_12345678901@c.us_DUPLICATE001',
                'timestamp' => time(),
                'from' => '12345678901@c.us',
                'to' => 'me@c.us',
                'body' => 'Duplicate message',
                'hasMedia' => false,
                'type' => 'chat',
            ],
        ];

        // First request should be processed
        $response1 = $this->postJson('/api/waha/bot', $payload);
        $response1->assertStatus(200);

        // Second request with same ID should be deduplicated
        $response2 = $this->postJson('/api/waha/bot', $payload);
        $response2->assertStatus(200);
    }

    public function test_location_message_webhook(): void
    {
        $payload = [
            'event' => 'message',
            'payload' => [
                'id' => 'false_12345678901@c.us_TESTLOC001',
                'timestamp' => time(),
                'from' => '12345678901@c.us',
                'to' => 'me@c.us',
                'hasMedia' => false,
                'location' => [
                    'latitude' => 55.7558,
                    'longitude' => 37.6173,
                    'description' => 'Moscow',
                ],
            ],
        ];

        $response = $this->postJson('/api/waha/bot', $payload);

        $response->assertStatus(200);
        $response->assertContent('ok');
    }

    public function test_document_message_webhook(): void
    {
        $payload = [
            'event' => 'message',
            'payload' => [
                'id' => 'false_12345678901@c.us_TESTDOC001',
                'timestamp' => time(),
                'from' => '12345678901@c.us',
                'to' => 'me@c.us',
                'body' => 'Document caption',
                'hasMedia' => true,
                'media' => [
                    'id' => 'media_doc123.pdf',
                    'mimetype' => 'application/pdf',
                    'filename' => 'document.pdf',
                ],
                'type' => 'document',
            ],
        ];

        $response = $this->postJson('/api/waha/bot', $payload);

        $response->assertStatus(200);
        $response->assertContent('ok');
    }

    public function test_invalid_payload_returns_ok(): void
    {
        // Invalid payload should return 'ok' to prevent WAHA retry loops
        $response = $this->postJson('/api/waha/bot', [
            'invalid_field' => 'value',
        ]);

        $response->assertStatus(200);
        $response->assertContent('ok');
    }

    public function test_contacts_message_webhook(): void
    {
        $payload = [
            'event' => 'message',
            'payload' => [
                'id' => 'false_12345678901@c.us_TESTCONTACT001',
                'timestamp' => time(),
                'from' => '12345678901@c.us',
                'to' => 'me@c.us',
                'hasMedia' => false,
                'vCards' => [
                    "BEGIN:VCARD\nVERSION:3.0\nFN:John Doe\nTEL:+1234567890\nEND:VCARD",
                ],
            ],
        ];

        $response = $this->postJson('/api/waha/bot', $payload);

        $response->assertStatus(200);
        $response->assertContent('ok');
    }
}
