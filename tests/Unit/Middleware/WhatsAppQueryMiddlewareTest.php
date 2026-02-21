<?php

namespace Tests\Unit\Middleware;

use App\Middleware\WhatsAppQuery;
use Illuminate\Http\Request;
use Tests\TestCase;

class WhatsAppQueryMiddlewareTest extends TestCase
{
    private string $appSecret = 'test_app_secret';

    public function setUp(): void
    {
        parent::setUp();

        config(['traffic_source.settings.whatsapp.app_secret' => $this->appSecret]);
    }

    public function test_valid_signature_passes(): void
    {
        $body = json_encode(['test' => 'data']);
        $signature = 'sha256=' . hash_hmac('sha256', $body, $this->appSecret);

        $request = Request::create('api/whatsapp/bot', 'POST', [], [], [], [
            'HTTP_X_HUB_SIGNATURE_256' => $signature,
        ], $body);

        $middleware = new WhatsAppQuery();
        $response = $middleware->handle($request, function ($req) {
            return response('ok', 200);
        });

        $this->assertEquals(200, $response->getStatusCode());
    }

    public function test_missing_signature_returns_200(): void
    {
        $request = Request::create('api/whatsapp/bot', 'POST', [], [], [], [], '{}');

        $middleware = new WhatsAppQuery();
        $response = $middleware->handle($request, function ($req) {
            return response('ok', 200);
        });

        $this->assertEquals(403, $response->getStatusCode());
    }

    public function test_invalid_signature_returns_200(): void
    {
        $body = json_encode(['test' => 'data']);

        $request = Request::create('api/whatsapp/bot', 'POST', [], [], [], [
            'HTTP_X_HUB_SIGNATURE_256' => 'sha256=invalidsignature',
        ], $body);

        $middleware = new WhatsAppQuery();
        $response = $middleware->handle($request, function ($req) {
            return response('ok', 200);
        });

        $this->assertEquals(403, $response->getStatusCode());
    }
}
