<?php

namespace Tests\Feature\Controllers;

use App\Models\BotUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class WhatsAppBotControllerTest extends TestCase
{
    use RefreshDatabase;

    private string $appSecret = 'test_app_secret';

    private string $verifyToken = 'test_verify_token';

    public function setUp(): void
    {
        parent::setUp();

        Queue::fake();
        Http::fake([
            'graph.facebook.com/*' => Http::response([
                'messaging_product' => 'whatsapp',
                'messages' => [['id' => 'wamid.test']],
            ], 200),
        ]);

        config([
            'traffic_source.settings.whatsapp.app_secret' => $this->appSecret,
            'traffic_source.settings.whatsapp.verify_token' => $this->verifyToken,
            'traffic_source.settings.whatsapp.token' => 'test_token',
            'traffic_source.settings.whatsapp.phone_number_id' => '123456789',
        ]);
    }

    public function test_webhook_verification_with_valid_token(): void
    {
        $response = $this->get('/api/whatsapp/bot?' . http_build_query([
            'hub_mode' => 'subscribe',
            'hub_verify_token' => $this->verifyToken,
            'hub_challenge' => 'challenge_string_123',
        ]));

        $response->assertStatus(200);
        $response->assertSee('challenge_string_123');
    }

    public function test_webhook_verification_with_invalid_token(): void
    {
        $response = $this->get('/api/whatsapp/bot?' . http_build_query([
            'hub_mode' => 'subscribe',
            'hub_verify_token' => 'wrong_token',
            'hub_challenge' => 'challenge_string_123',
        ]));

        $response->assertStatus(403);
    }

    public function test_incoming_text_message_creates_bot_user(): void
    {
        $payload = $this->getTextMessagePayload();
        $signature = $this->generateSignature($payload);

        $response = $this->withHeaders([
            'X-Hub-Signature-256' => $signature,
        ])->postJson('/api/whatsapp/bot', $payload);

        $response->assertStatus(200);

        $this->assertDatabaseHas('bot_users', [
            'chat_id' => '15559876543',
            'platform' => 'whatsapp',
        ]);
    }

    public function test_duplicate_message_is_ignored(): void
    {
        $payload = $this->getTextMessagePayload();
        $signature = $this->generateSignature($payload);
        $messageId = $payload['entry'][0]['changes'][0]['value']['messages'][0]['id'];

        Cache::put('wa_event_' . $messageId, true, 600);

        $response = $this->withHeaders([
            'X-Hub-Signature-256' => $signature,
        ])->postJson('/api/whatsapp/bot', $payload);

        $response->assertStatus(200);
    }

    public function test_banned_user_gets_blocked(): void
    {
        $botUser = BotUser::create([
            'chat_id' => '15559876543',
            'platform' => 'whatsapp',
            'is_banned' => true,
            'banned_at' => now(),
        ]);

        $payload = $this->getTextMessagePayload();
        $signature = $this->generateSignature($payload);

        $response = $this->withHeaders([
            'X-Hub-Signature-256' => $signature,
        ])->postJson('/api/whatsapp/bot', $payload);

        $response->assertStatus(200);
    }

    public function test_missing_signature_returns_403(): void
    {
        $payload = $this->getTextMessagePayload();

        $response = $this->postJson('/api/whatsapp/bot', $payload);

        $response->assertStatus(403);
    }

    public function test_invalid_signature_returns_403(): void
    {
        $payload = $this->getTextMessagePayload();

        $response = $this->withHeaders([
            'X-Hub-Signature-256' => 'sha256=invalidsignature',
        ])->postJson('/api/whatsapp/bot', $payload);

        $response->assertStatus(403);
    }

    private function getTextMessagePayload(): array
    {
        return [
            'object' => 'whatsapp_business_account',
            'entry' => [
                [
                    'id' => '123456789',
                    'changes' => [
                        [
                            'value' => [
                                'messaging_product' => 'whatsapp',
                                'metadata' => [
                                    'display_phone_number' => '15551234567',
                                    'phone_number_id' => '123456789',
                                ],
                                'contacts' => [
                                    [
                                        'profile' => ['name' => 'Test User'],
                                        'wa_id' => '15559876543',
                                    ],
                                ],
                                'messages' => [
                                    [
                                        'from' => '15559876543',
                                        'id' => 'wamid.unique_' . time(),
                                        'timestamp' => (string) time(),
                                        'text' => ['body' => 'Hello from WhatsApp'],
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

    private function generateSignature(array $payload): string
    {
        $body = json_encode($payload);

        return 'sha256=' . hash_hmac('sha256', $body, $this->appSecret);
    }
}
