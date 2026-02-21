<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

class ValidateWahaConnection extends Command
{
    private const READY_STATUSES = ['AUTHENTICATED', 'WORKING'];

    protected $signature = 'waha:validate
                            {host? : WAHA server hostname (e.g., localhost:3000)}
                            {api-key? : WAHA API key}
                            {session=default : WAHA session name}
                            {--basic-auth= : Basic auth credentials (user:pass)}';

    protected $description = 'Validate WAHA (WhatsApp HTTP API) connection and configuration';

    public function handle(): int
    {
        $host = (string) ($this->argument('host') ?? config('traffic_source.settings.whatsapp.waha.base_url', 'http://localhost:3000'));
        $apiKey = $this->argument('api-key') ?: config('traffic_source.settings.whatsapp.waha.api_key');
        $session = (string) $this->argument('session');
        $basicAuth = $this->option('basic-auth') ?: config('traffic_source.settings.whatsapp.waha.basic_auth');

        $this->info('🔍 WAHA Connection Validation');
        $this->info('================================');
        $this->newLine();

        $this->info('Step 1: Testing server connectivity...');
        if (! $this->testConnectivity($host, $apiKey, $basicAuth)) {
            $this->error('❌ Cannot connect to WAHA server at ' . $host);

            return self::FAILURE;
        }

        $this->info('✅ Server is reachable');
        $this->newLine();

        $this->info('Step 2: Getting server status...');
        $status = $this->getServerStatus($host, $apiKey, $basicAuth);

        if ($status === null) {
            $this->warn('⚠️ Could not retrieve server status');
        } else {
            $this->info('✅ Server version: ' . ($status['version'] ?? 'unknown'));
            $this->info('✅ Engine: ' . ($status['engine'] ?? 'unknown'));
        }

        $this->newLine();

        $this->info('Step 3: Checking session "' . $session . '" status...');
        $sessionStatus = $this->getSessionStatus($host, $apiKey, $basicAuth, $session);

        if ($sessionStatus === null) {
            $this->error('❌ Could not retrieve session status');

            return self::FAILURE;
        }

        $currentStatus = (string) ($sessionStatus['status'] ?? 'unknown');

        if (in_array($currentStatus, self::READY_STATUSES, true)) {
            $this->info('✅ Session is ' . $currentStatus);
            $this->info('   Phone: ' . ($sessionStatus['me']['id'] ?? 'unknown'));
        } elseif ($currentStatus === 'SCAN_QR') {
            $this->warn('⚠️ Session requires QR code scan');
            $this->info('   Visit: ' . $host . '/api/' . $session . '/auth/qr');
        } else {
            $this->warn('⚠️ Session status: ' . $currentStatus);
        }

        $this->newLine();

        if ($this->confirm('Do you want to test sending a message? (requires test phone number)', false)) {
            $phoneNumber = $this->ask('Enter test phone number (e.g., 12345678901)');

            if ($phoneNumber !== null && $phoneNumber !== '') {
                $this->testSendMessage($host, $apiKey, $basicAuth, $session, $phoneNumber);
            }
        }

        return $this->printSummary($host, $apiKey, $session, $basicAuth, $currentStatus);
    }

    private function printSummary(string $host, mixed $apiKey, string $session, mixed $basicAuth, string $sessionStatus): int
    {
        $this->newLine();
        $this->info('📋 Summary');
        $this->info('================================');
        $this->info('Host: ' . $host);
        $this->info('Session: ' . $session);
        $this->info('API Key: ' . ($apiKey ? '✅ Configured' : '⚠️ Not configured'));
        $this->info('Basic Auth: ' . ($basicAuth ? '✅ Configured' : '⚠️ Not configured'));

        if (! in_array($sessionStatus, self::READY_STATUSES, true)) {
            $this->newLine();
            $this->warn('⚠️ WAHA is not fully configured');
            $this->info('Please authenticate the session first');

            return self::SUCCESS;
        }

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

    private function testConnectivity(string $host, ?string $apiKey, ?string $basicAuth): bool
    {
        try {
            return $this->http($apiKey, $basicAuth)
                ->timeout(5)
                ->get($this->url($host, '/api/server/status'))
                ->successful();
        } catch (\Throwable $exception) {
            $this->error('Connection error: ' . $exception->getMessage());

            return false;
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    private function getServerStatus(string $host, ?string $apiKey, ?string $basicAuth): ?array
    {
        try {
            return $this->http($apiKey, $basicAuth)
                ->get($this->url($host, '/api/server/status'))
                ->json();
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    private function getSessionStatus(string $host, ?string $apiKey, ?string $basicAuth, string $session): ?array
    {
        try {
            return $this->http($apiKey, $basicAuth)
                ->get($this->url($host, '/api/sessions/' . $session))
                ->json();
        } catch (\Throwable $exception) {
            $this->error('Error: ' . $exception->getMessage());

            return null;
        }
    }

    private function testSendMessage(string $host, ?string $apiKey, ?string $basicAuth, string $session, string $phoneNumber): void
    {
        $this->info('Sending test message to ' . $phoneNumber . '...');

        try {
            $response = $this->http($apiKey, $basicAuth)->post($this->url($host, '/api/sendText'), [
                'session' => $session,
                'chatId' => $phoneNumber . '@c.us',
                'text' => "🔧 WAHA Test Message\nThis is a test from tg-support-bot validation.",
            ]);

            if (! $response->successful()) {
                $this->error('❌ Failed to send test message');
                $this->error('   Status: ' . $response->status());
                $this->error('   Response: ' . $response->body());

                return;
            }

            $this->info('✅ Test message sent successfully');
            $this->info('   Message ID: ' . ($response->json('id') ?? 'unknown'));
        } catch (\Throwable $exception) {
            $this->error('❌ Error: ' . $exception->getMessage());
        }
    }

    private function http(?string $apiKey, ?string $basicAuth): PendingRequest
    {
        return Http::withHeaders($this->buildHeaders($apiKey, $basicAuth));
    }

    /**
     * @return array<string, string>
     */
    private function buildHeaders(?string $apiKey, ?string $basicAuth): array
    {
        $headers = ['Content-Type' => 'application/json'];

        if ($apiKey !== null && $apiKey !== '') {
            $headers['X-Api-Key'] = $apiKey;
        }

        if ($basicAuth !== null && $basicAuth !== '') {
            $headers['Authorization'] = 'Basic ' . base64_encode($basicAuth);
        }

        return $headers;
    }

    private function url(string $host, string $path): string
    {
        return rtrim($host, '/') . $path;
    }
}
