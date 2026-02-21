<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class WahaValidationController
{
    /**
     * Run full WAHA validation with provided credentials.
     *
     * @param Request $request
     *
     * @return JsonResponse
     */
    public function validateConnection(Request $request): JsonResponse
    {
        $host = $request->input('host', config('traffic_source.settings.whatsapp.waha.base_url', 'http://localhost:3000'));
        $apiKey = $request->input('api_key', config('traffic_source.settings.whatsapp.waha.api_key'));
        $basicAuth = $request->input('basic_auth', config('traffic_source.settings.whatsapp.waha.basic_auth'));
        $session = $request->input('session', 'default');

        $results = [
            'host' => $host,
            'session' => $session,
            'api_key_configured' => !empty($apiKey),
            'basic_auth_configured' => !empty($basicAuth),
            'tests' => [],
        ];

        // Test 1: Connectivity
        try {
            $headers = $this->buildHeaders($apiKey, $basicAuth);

            $response = Http::withHeaders($headers)
                ->timeout(10)
                ->get(rtrim($host, '/') . '/api/server/status');

            $results['tests']['connectivity'] = [
                'passed' => $response->successful(),
                'status' => $response->status(),
                'data' => $response->json(),
            ];
        } catch (\Throwable $e) {
            $results['tests']['connectivity'] = [
                'passed' => false,
                'error' => $e->getMessage(),
            ];

            return response()->json($results, 200);
        }

        // Test 2: Session Status
        try {
            $headers = $this->buildHeaders($apiKey, $basicAuth);

            $response = Http::withHeaders($headers)
                ->get(rtrim($host, '/') . '/api/sessions/' . $session);

            $sessionData = $response->json();
            $results['tests']['session'] = [
                'passed' => $response->successful() && isset($sessionData['status']),
                'status' => $sessionData['status'] ?? 'unknown',
                'authenticated' => ($sessionData['status'] ?? '') === 'AUTHENTICATED',
                'phone' => $sessionData['me']['id'] ?? null,
            ];
        } catch (\Throwable $e) {
            $results['tests']['session'] = [
                'passed' => false,
                'error' => $e->getMessage(),
            ];
        }

        // Test 3: Send Test Message (if phone provided)
        $testPhone = $request->input('test_phone');
        if ($testPhone) {
            try {
                $headers = $this->buildHeaders($apiKey, $basicAuth);

                $response = Http::withHeaders($headers)
                    ->post(rtrim($host, '/') . '/api/sendText', [
                        'session' => $session,
                        'chatId' => $testPhone . '@c.us',
                        'text' => "🔧 WAHA Validation Test\nServer: " . config('app.url') . "\nTime: " . now(),
                    ]);

                $results['tests']['send_message'] = [
                    'passed' => $response->successful(),
                    'status' => $response->status(),
                    'message_id' => $response->json('id'),
                ];
            } catch (\Throwable $e) {
                $results['tests']['send_message'] = [
                    'passed' => false,
                    'error' => $e->getMessage(),
                ];
            }
        }

        // Determine overall status
        $allPassed = collect($results['tests'])
            ->every(fn ($test) => $test['passed']);

        $results['overall_status'] = $allPassed ? 'success' : 'partial';
        $results['ready_to_use'] = ($results['tests']['session']['authenticated'] ?? false);

        // Generate env configuration
        if ($results['ready_to_use']) {
            $results['env_config'] = [
                'WHATSAPP_PROVIDER' => 'waha',
                'WAHA_BASE_URL' => $host,
                'WAHA_API_KEY' => $apiKey ?: 'your_api_key',
                'WAHA_SESSION' => $session,
            ];
            if ($basicAuth) {
                $results['env_config']['WAHA_BASIC_AUTH'] = $basicAuth;
            }
        }

        return response()->json($results, 200);
    }

    /**
     * Quick health check endpoint.
     *
     * @return JsonResponse
     */
    public function health(): JsonResponse
    {
        $host = config('traffic_source.settings.whatsapp.waha.base_url', 'http://localhost:3000');
        $apiKey = config('traffic_source.settings.whatsapp.waha.api_key');
        $basicAuth = config('traffic_source.settings.whatsapp.waha.basic_auth');

        try {
            $headers = $this->buildHeaders($apiKey, $basicAuth);

            $response = Http::withHeaders($headers)
                ->timeout(5)
                ->get(rtrim($host, '/') . '/api/server/status');

            return response()->json([
                'status' => $response->successful() ? 'healthy' : 'unhealthy',
                'waha_status' => $response->json(),
            ], $response->successful() ? 200 : 503);
        } catch (\Throwable $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 503);
        }
    }

    /**
     * Build request headers with optional API key and Basic Auth.
     *
     * @param string|null $apiKey
     * @param string|null $basicAuth
     *
     * @return array
     */
    private function buildHeaders(?string $apiKey, ?string $basicAuth): array
    {
        $headers = ['Content-Type' => 'application/json'];

        if ($apiKey) {
            $headers['X-Api-Key'] = $apiKey;
        }

        if ($basicAuth) {
            $headers['Authorization'] = 'Basic ' . base64_encode($basicAuth);
        }

        return $headers;
    }
}
