<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class WahaValidationController
{
    public function validateConnection(Request $request): JsonResponse
    {
        $host = (string) $request->input('host', config('traffic_source.settings.whatsapp.waha.base_url', 'http://localhost:3000'));
        $apiKey = $request->input('api_key', config('traffic_source.settings.whatsapp.waha.api_key'));
        $basicAuth = $request->input('basic_auth', config('traffic_source.settings.whatsapp.waha.basic_auth'));
        $session = (string) $request->input('session', 'default');

        $results = [
            'host' => $host,
            'session' => $session,
            'api_key_configured' => ! empty($apiKey),
            'basic_auth_configured' => ! empty($basicAuth),
            'tests' => [],
        ];

        try {
            $response = $this->http($apiKey, $basicAuth)
                ->timeout(10)
                ->get($this->url($host, '/api/server/status'));

            $results['tests']['connectivity'] = [
                'passed' => $response->successful(),
                'status' => $response->status(),
                'data' => $response->json(),
            ];
        } catch (\Throwable $exception) {
            $results['tests']['connectivity'] = [
                'passed' => false,
                'error' => $exception->getMessage(),
            ];

            return response()->json($results, 200);
        }

        try {
            $response = $this->http($apiKey, $basicAuth)
                ->get($this->url($host, '/api/sessions/' . $session));

            /** @var array<string, mixed> $sessionData */
            $sessionData = $response->json() ?? [];

            $results['tests']['session'] = [
                'passed' => $response->successful() && isset($sessionData['status']),
                'status' => $sessionData['status'] ?? 'unknown',
                'authenticated' => ($sessionData['status'] ?? '') === 'AUTHENTICATED',
                'phone' => $sessionData['me']['id'] ?? null,
            ];
        } catch (\Throwable $exception) {
            $results['tests']['session'] = [
                'passed' => false,
                'error' => $exception->getMessage(),
            ];
        }

        $testPhone = $request->input('test_phone');

        if (is_string($testPhone) && $testPhone !== '') {
            try {
                $response = $this->http($apiKey, $basicAuth)
                    ->post($this->url($host, '/api/sendText'), [
                        'session' => $session,
                        'chatId' => $testPhone . '@c.us',
                        'text' => "🔧 WAHA Validation Test\nServer: " . config('app.url') . "\nTime: " . now(),
                    ]);

                $results['tests']['send_message'] = [
                    'passed' => $response->successful(),
                    'status' => $response->status(),
                    'message_id' => $response->json('id'),
                ];
            } catch (\Throwable $exception) {
                $results['tests']['send_message'] = [
                    'passed' => false,
                    'error' => $exception->getMessage(),
                ];
            }
        }

        $allPassed = collect($results['tests'])->every(
            static fn (array $test): bool => (bool) $test['passed']
        );

        $readyToUse = (bool) ($results['tests']['session']['authenticated'] ?? false);

        $results['overall_status'] = $allPassed ? 'success' : 'partial';
        $results['ready_to_use'] = $readyToUse;

        if ($readyToUse) {
            $results['env_config'] = [
                'WHATSAPP_PROVIDER' => 'waha',
                'WAHA_BASE_URL' => $host,
                'WAHA_API_KEY' => $apiKey ?: 'your_api_key',
                'WAHA_SESSION' => $session,
            ];

            if (is_string($basicAuth) && $basicAuth !== '') {
                $results['env_config']['WAHA_BASIC_AUTH'] = $basicAuth;
            }
        }

        return response()->json($results, 200);
    }

    public function health(): JsonResponse
    {
        $host = (string) config('traffic_source.settings.whatsapp.waha.base_url', 'http://localhost:3000');
        $apiKey = config('traffic_source.settings.whatsapp.waha.api_key');
        $basicAuth = config('traffic_source.settings.whatsapp.waha.basic_auth');

        try {
            $response = $this->http($apiKey, $basicAuth)
                ->timeout(5)
                ->get($this->url($host, '/api/server/status'));

            return response()->json([
                'status' => $response->successful() ? 'healthy' : 'unhealthy',
                'waha_status' => $response->json(),
            ], $response->successful() ? 200 : 503);
        } catch (\Throwable $exception) {
            return response()->json([
                'status' => 'error',
                'message' => $exception->getMessage(),
            ], 503);
        }
    }

    public function testImage(Request $request): JsonResponse
    {
        $host = (string) $request->input('host', config('traffic_source.settings.whatsapp.waha.base_url', 'http://localhost:3000'));
        $apiKey = $request->input('api_key', config('traffic_source.settings.whatsapp.waha.api_key'));
        $basicAuth = $request->input('basic_auth', config('traffic_source.settings.whatsapp.waha.basic_auth'));
        $session = (string) $request->input('session', 'default');
        $testPhone = (string) $request->input('test_phone', '79956572287');

        try {
            // Create a simple test image (1x1 pixel PNG)
            $pngData = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==');
            $tempFile = sys_get_temp_dir() . '/test_image_' . uniqid() . '.png';
            file_put_contents($tempFile, $pngData);

            // Convert to base64 data URI (same as uploadMedia does)
            $base64 = base64_encode($pngData);
            $dataUri = 'data:image/png;base64,' . $base64;

            // Send via WAHA sendImage endpoint
            $response = $this->http($apiKey, $basicAuth)
                ->post($this->url($host, '/api/sendImage'), [
                    'session' => $session,
                    'chatId' => $testPhone . '@c.us',
                    'file' => $dataUri,
                    'caption' => '🔧 Test image from tg-support-bot validation',
                ]);

            @unlink($tempFile);

            return response()->json([
                'success' => $response->successful(),
                'status' => $response->status(),
                'response' => $response->json(),
                'test_phone' => $testPhone,
            ], $response->successful() ? 200 : 500);
        } catch (\Throwable $exception) {
            return response()->json([
                'success' => false,
                'error' => $exception->getMessage(),
            ], 500);
        }
    }

    private function http(mixed $apiKey, mixed $basicAuth): PendingRequest
    {
        return Http::withHeaders($this->buildHeaders($apiKey, $basicAuth));
    }

    /**
     * @return array<string, string>
     */
    private function buildHeaders(mixed $apiKey, mixed $basicAuth): array
    {
        $headers = ['Content-Type' => 'application/json'];

        if (is_string($apiKey) && $apiKey !== '') {
            $headers['X-Api-Key'] = $apiKey;
        }

        if (is_string($basicAuth) && $basicAuth !== '') {
            $headers['Authorization'] = 'Basic ' . base64_encode($basicAuth);
        }

        return $headers;
    }

    private function url(string $host, string $path): string
    {
        return rtrim($host, '/') . $path;
    }
}
