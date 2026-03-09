<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

class ConnectGowaDevice extends Command
{
    protected $signature = 'gowa:connect
                            {host? : GOWA server URL (e.g., http://localhost:3000)}
                            {--device-id= : Device ID to connect (optional, uses default if not set)}
                            {--basic-auth= : Basic auth credentials (user:pass)}
                            {--pairing-code : Use pairing code instead of QR code}
                            {--phone= : Phone number for pairing code login (e.g., 628912344551)}';

    protected $description = 'Connect a device to WhatsApp via go-whatsapp-web-multidevice (GOWA) using QR code or pairing code';

    public function handle(): int
    {
        $host = (string) ($this->argument('host') ?? config('traffic_source.settings.whatsapp.gowa.base_url', 'http://localhost:3000'));
        $deviceId = $this->option('device-id') ?: config('traffic_source.settings.whatsapp.gowa.device_id', '');
        $basicAuth = $this->option('basic-auth') ?: config('traffic_source.settings.whatsapp.gowa.basic_auth', '');

        $this->info('🔗 GOWA Device Connection');
        $this->info('================================');
        $this->newLine();

        $this->info('Step 1: Checking GOWA server connectivity...');
        if (! $this->testConnectivity($host, $basicAuth)) {
            $this->error('❌ Cannot connect to GOWA server at ' . $host);
            $this->error('   Make sure GOWA is running: docker ps | grep gowa');

            return self::FAILURE;
        }

        $this->info('✅ Server is reachable');
        $this->newLine();

        $this->info('Step 2: Checking current connection status...');
        $status = $this->getDeviceStatus($host, $basicAuth, $deviceId);

        if ($status !== null) {
            $isConnected = (bool) ($status['results']['is_connected'] ?? false);
            $isLoggedIn = (bool) ($status['results']['is_logged_in'] ?? false);

            if ($isConnected && $isLoggedIn) {
                $this->info('✅ Device is already connected and logged in!');
                $this->info('   Device ID: ' . ($status['results']['device_id'] ?? 'unknown'));
                $this->newLine();

                if (! $this->confirm('Do you want to reconnect anyway?', false)) {
                    return $this->printSummary($host, $deviceId, $basicAuth);
                }
            } else {
                $this->warn('⚠️  Device is not fully connected (connected=' . ($isConnected ? 'yes' : 'no') . ', logged_in=' . ($isLoggedIn ? 'yes' : 'no') . ')');
            }
        }

        $this->newLine();

        if ($this->option('pairing-code')) {
            return $this->loginWithPairingCode($host, $basicAuth, $deviceId);
        }

        return $this->loginWithQrCode($host, $basicAuth, $deviceId);
    }

    private function loginWithQrCode(string $host, string $basicAuth, string $deviceId): int
    {
        $this->info('Step 3: Requesting QR code login...');

        $endpoint = $deviceId !== ''
            ? '/devices/' . $deviceId . '/login'
            : '/app/login';

        try {
            $response = $this->http($basicAuth)
                ->timeout(30)
                ->get($this->url($host, $endpoint));

            if (! $response->successful()) {
                $this->error('❌ Failed to initiate login: ' . $response->status());
                $this->error('   Response: ' . $response->body());

                return self::FAILURE;
            }

            $data = $response->json();
            $qrCode = $data['results']['qr_duration'] ?? null;
            $qrLink = $data['results']['qr_link'] ?? null;
            $qrBase64 = $data['results']['qr'] ?? null;

            $this->newLine();
            $this->info('📱 QR Code Login');
            $this->info('================================');

            if ($qrLink !== null) {
                $this->info('QR Code URL: ' . $qrLink);
            }

            if ($qrBase64 !== null) {
                $this->info('QR Code (base64 PNG): ' . substr((string) $qrBase64, 0, 50) . '...');
                $this->info('');
                $this->info('To view the QR code, open this URL in your browser:');
                $this->info($this->url($host, '/') . ' (GOWA web interface)');
            }

            $this->newLine();
            $this->warn('⚠️  Open WhatsApp on your phone → Settings → Linked Devices → Link a Device');
            $this->warn('   Scan the QR code shown in the GOWA web interface at: ' . $host);

            if ($qrCode !== null) {
                $this->info('   QR code expires in: ' . $qrCode . ' seconds');
            }

            $this->newLine();
            $this->info('Waiting for you to scan the QR code...');
            $this->info('(Press Ctrl+C to cancel, then run this command again if needed)');
            $this->newLine();

            return $this->waitForConnection($host, $basicAuth, $deviceId);
        } catch (\Throwable $exception) {
            $this->error('❌ Error: ' . $exception->getMessage());

            return self::FAILURE;
        }
    }

    private function loginWithPairingCode(string $host, string $basicAuth, string $deviceId): int
    {
        $phone = $this->option('phone') ?: $this->ask('Enter your WhatsApp phone number (with country code, e.g., 628912344551)');

        if (empty($phone)) {
            $this->error('❌ Phone number is required for pairing code login');

            return self::FAILURE;
        }

        $this->info('Step 3: Requesting pairing code for phone ' . $phone . '...');

        $endpoint = $deviceId !== ''
            ? '/devices/' . $deviceId . '/login/code?phone=' . $phone
            : '/app/login-with-code?phone=' . $phone;

        try {
            $response = $this->http($basicAuth)
                ->timeout(30)
                ->get($this->url($host, $endpoint));

            if (! $response->successful()) {
                $this->error('❌ Failed to get pairing code: ' . $response->status());
                $this->error('   Response: ' . $response->body());

                return self::FAILURE;
            }

            $data = $response->json();
            $pairingCode = $data['results']['code'] ?? null;

            if ($pairingCode === null) {
                $this->error('❌ No pairing code returned');
                $this->error('   Response: ' . json_encode($data));

                return self::FAILURE;
            }

            $this->newLine();
            $this->info('📱 Pairing Code Login');
            $this->info('================================');
            $this->info('Your pairing code: ' . $pairingCode);
            $this->newLine();
            $this->warn('⚠️  Open WhatsApp on your phone → Settings → Linked Devices → Link a Device → Link with phone number');
            $this->warn('   Enter the pairing code: ' . $pairingCode);
            $this->newLine();

            return $this->waitForConnection($host, $basicAuth, $deviceId);
        } catch (\Throwable $exception) {
            $this->error('❌ Error: ' . $exception->getMessage());

            return self::FAILURE;
        }
    }

    private function waitForConnection(string $host, string $basicAuth, string $deviceId): int
    {
        $maxAttempts = 30;
        $interval = 3;

        for ($i = 0; $i < $maxAttempts; $i++) {
            sleep($interval);

            $status = $this->getDeviceStatus($host, $basicAuth, $deviceId);

            if ($status === null) {
                continue;
            }

            $isConnected = (bool) ($status['results']['is_connected'] ?? false);
            $isLoggedIn = (bool) ($status['results']['is_logged_in'] ?? false);

            if ($isConnected && $isLoggedIn) {
                $this->newLine();
                $this->info('✅ Successfully connected!');
                $this->info('   Device ID: ' . ($status['results']['device_id'] ?? 'unknown'));

                return $this->printSummary($host, $deviceId, $basicAuth);
            }

            $this->output->write('.');
        }

        $this->newLine();
        $this->error('❌ Timed out waiting for connection. Please try again.');

        return self::FAILURE;
    }

    private function printSummary(string $host, string $deviceId, string $basicAuth): int
    {
        $this->newLine();
        $this->info('📋 Configuration Summary');
        $this->info('================================');
        $this->info('Add these to your .env file:');
        $this->newLine();
        $this->info('WHATSAPP_PROVIDER=gowa');
        $this->info('GOWA_BASE_URL=' . $host);

        if ($deviceId !== '') {
            $this->info('GOWA_DEVICE_ID=' . $deviceId);
        }

        if ($basicAuth !== '') {
            $this->info('GOWA_BASIC_AUTH=' . $basicAuth);
        }

        $this->newLine();
        $this->info('Webhook URL to configure in GOWA:');
        $this->info(config('app.url') . '/api/gowa/bot');
        $this->newLine();
        $this->info('Start GOWA with webhook:');
        $this->info('./whatsapp rest --webhook="' . config('app.url') . '/api/gowa/bot"');

        return self::SUCCESS;
    }

    private function testConnectivity(string $host, string $basicAuth): bool
    {
        try {
            return $this->http($basicAuth)
                ->timeout(5)
                ->get($this->url($host, '/app/devices'))
                ->successful();
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    private function getDeviceStatus(string $host, string $basicAuth, string $deviceId): ?array
    {
        try {
            $endpoint = $deviceId !== ''
                ? '/devices/' . $deviceId . '/status'
                : '/app/status';

            return $this->http($basicAuth)
                ->timeout(5)
                ->get($this->url($host, $endpoint))
                ->json();
        } catch (\Throwable) {
            return null;
        }
    }

    private function http(string $basicAuth): PendingRequest
    {
        $headers = [];

        if ($basicAuth !== '') {
            $headers['Authorization'] = 'Basic ' . base64_encode($basicAuth);
        }

        return Http::withHeaders($headers);
    }

    private function url(string $host, string $path): string
    {
        return rtrim($host, '/') . $path;
    }
}
