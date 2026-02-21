<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class ValidateWahaConnection extends Command
{
    protected $signature = 'waha:validate
                            {host? : WAHA server hostname (e.g., localhost:3000)}
                            {api-key? : WAHA API key}
                            {session=default : WAHA session name}
                            {--basic-auth= : Basic auth credentials (user:pass)}';

    protected $description = 'Validate WAHA (WhatsApp HTTP API) connection and configuration';

    public function handle(): int
    {
        $host = $this->argument('host') ?? config('traffic_source.settings.whatsapp.waha.base_url', 'http://localhost:3000');
        $apiKey = $this->argument('api-key') ?? config('traffic_source.settings.whatsapp.waha.api_key');
        $session = $this->argument('session');
        $basicAuth = $this->option('basic-auth') ?? config('traffic_source.settings.whatsapp.waha.basic_auth');

        $this->info('🔍 WAHA Connection Validation');
        $this->info('================================');
        $this->newLine();

        // Step 1: Validate server connectivity
        $this->info('Step 1: Testing server connectivity...');
        if (!$this->testConnectivity($host, $apiKey, $basicAuth)) {
            $this->error('❌ Cannot connect to WAHA server at ' . $host);

            return self::FAILURE;
        }
        $this->info('✅ Server is reachable');
        $this->newLine();

        // Step 2: Get server status
        $this->info('Step 2: Getting server status...');
        $status = $this->getServerStatus($host, $apiKey, $basicAuth);
        if (!$status) {
            $this->warn('⚠️ Could not retrieve server status');
        } else {
            $this->info('✅ Server version: ' . ($status['version'] ?? 'unknown'));
            $this->info('✅ Engine: ' . ($status['engine'] ?? 'unknown'));
        }
        $this->newLine();

        // Step 3: Check session status
        $this->info('Step 3: Checking session "' . $session . '" status...');
        $sessionStatus = $this->getSessionStatus($host, $apiKey, $basicAuth, $session);
        if (!$sessionStatus) {
            $this->error('❌ Could not retrieve session status');

            return self::FAILURE;
        }

        if ($sessionStatus['status'] === 'AUTHENTICATED' || $sessionStatus['status'] === 'WORKING') {
            $this->info('✅ Session is ' . $sessionStatus['status']);
            $this->info('   Phone: ' . ($sessionStatus['me']['id'] ?? 'unknown'));
        } elseif ($sessionStatus['status'] === 'SCAN_QR') {
            $this->warn('⚠️ Session requires QR code scan');
            $this->info('   Visit: ' . $host . '/api/' . $session . '/auth/qr');
        } else {
            $this->warn('⚠️ Session status: ' . $sessionStatus['status']);
        }
        $this->newLine();

        // Step 4: Test sending a message (optional)
        if ($this->confirm('Do you want to test sending a message? (requires test phone number)', false)) {
            $phoneNumber = $this->ask('Enter test phone number (e.g., 12345678901)');
            $this->testSendMessage($host, $apiKey, $basicAuth, $session, $phoneNumber);
        }

        // Step 5: Summary
        $this->newLine();
        $this->info('📋 Summary');
        $this->info('================================');
        $this->info('Host: ' . $host);
        $this->info('Session: ' . $session);
        $this->info('API Key: ' . ($apiKey ? '✅ Configured' : '⚠️ Not configured'));
        $this->info('Basic Auth: ' . ($basicAuth ? '✅ Configured' : '⚠️ Not configured'));

        if (in_array($sessionStatus['status'], ['AUTHENTICATED', 'WORKING'])) {
            $this->newLine();
            $this->info('✅ WAHA is ready to use!');
            $this->info('Add these to your .env file:');
            $this->info('WHATSAPP_PROVIDER=waha');
            $this->info('WAHA_BASE_URL=' . $host);
            $this->info('WAHA_API_KEY=' . ($apiKey ?: 'your_api_key'));
            $this->info('WAHA_SESSION=' . $session);
            if ($basicAuth) {
                $this->info('WAHA_BASIC_AUTH=' . $basicAuth);
            }

            return self::SUCCESS;
        }

        $this->newLine();
        $this->warn('⚠️ WAHA is not fully configured');
        $this->info('Please authenticate the session first');

        return self::SUCCESS;
    }

    private function testConnectivity(string $host, ?string $apiKey, ?string $basicAuth): bool
    {
        try {
            $headers = ['Content-Type' => 'application/json'];
            if ($apiKey) {
                $headers['X-Api-Key'] = $apiKey;
            }
            if ($basicAuth) {
                $headers['Authorization'] = 'Basic ' . base64_encode($basicAuth);
            }

            $response = Http::withHeaders($headers)
                ->timeout(5)
                ->get(rtrim($host, '/') . '/api/server/status');

            return $response->successful();
        } catch (\Throwable $e) {
            $this->error('Connection error: ' . $e->getMessage());

            return false;
        }
    }

    private function getServerStatus(string $host, ?string $apiKey, ?string $basicAuth): ?array
    {
        try {
            $headers = ['Content-Type' => 'application/json'];
            if ($apiKey) {
                $headers['X-Api-Key'] = $apiKey;
            }
            if ($basicAuth) {
                $headers['Authorization'] = 'Basic ' . base64_encode($basicAuth);
            }

            $response = Http::withHeaders($headers)
                ->get(rtrim($host, '/') . '/api/server/status');

            return $response->json();
        } catch (\Throwable) {
            return null;
        }
    }

    private function getSessionStatus(string $host, ?string $apiKey, ?string $basicAuth, string $session): ?array
    {
        try {
            $headers = ['Content-Type' => 'application/json'];
            if ($apiKey) {
                $headers['X-Api-Key'] = $apiKey;
            }
            if ($basicAuth) {
                $headers['Authorization'] = 'Basic ' . base64_encode($basicAuth);
            }

            $response = Http::withHeaders($headers)
                ->get(rtrim($host, '/') . '/api/sessions/' . $session);

            return $response->json();
        } catch (\Throwable $e) {
            $this->error('Error: ' . $e->getMessage());

            return null;
        }
    }

    private function testSendMessage(string $host, ?string $apiKey, ?string $basicAuth, string $session, string $phoneNumber): void
    {
        $this->info('Sending test message to ' . $phoneNumber . '...');

        try {
            $headers = ['Content-Type' => 'application/json'];
            if ($apiKey) {
                $headers['X-Api-Key'] = $apiKey;
            }
            if ($basicAuth) {
                $headers['Authorization'] = 'Basic ' . base64_encode($basicAuth);
            }

            $response = Http::withHeaders($headers)
                ->post(rtrim($host, '/') . '/api/sendText', [
                    'session' => $session,
                    'chatId' => $phoneNumber . '@c.us',
                    'text' => "🔧 WAHA Test Message\nThis is a test from tg-support-bot validation.",
                ]);

            if ($response->successful()) {
                $this->info('✅ Test message sent successfully');
                $data = $response->json();
                $this->info('   Message ID: ' . ($data['id'] ?? 'unknown'));
            } else {
                $this->error('❌ Failed to send test message');
                $this->error('   Status: ' . $response->status());
                $this->error('   Response: ' . $response->body());
            }
        } catch (\Throwable $e) {
            $this->error('❌ Error: ' . $e->getMessage());
        }
    }
}
