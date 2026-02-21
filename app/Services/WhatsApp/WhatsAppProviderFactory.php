<?php

declare(strict_types=1);

namespace App\Services\WhatsApp;

use App\Contracts\WhatsApp\WhatsAppProviderInterface;
use App\Services\WhatsApp\Providers\CloudApiProvider;
use App\Services\WhatsApp\Providers\WahaProvider;

class WhatsAppProviderFactory
{
    /**
     * Create the appropriate WhatsApp provider based on configuration.
     *
     * @return WhatsAppProviderInterface
     */
    public static function make(): WhatsAppProviderInterface
    {
        $provider = config('traffic_source.settings.whatsapp.provider', 'cloud');

        return match ($provider) {
            'waha' => new WahaProvider(),
            default => new CloudApiProvider(),
        };
    }

    /**
     * Get the name of the currently configured provider.
     *
     * @return string
     */
    public static function getProviderName(): string
    {
        return config('traffic_source.settings.whatsapp.provider', 'cloud');
    }

    /**
     * Check if WAHA provider is configured.
     *
     * @return bool
     */
    public static function isWaha(): bool
    {
        return self::getProviderName() === 'waha';
    }

    /**
     * Check if Cloud API provider is configured.
     *
     * @return bool
     */
    public static function isCloudApi(): bool
    {
        return self::getProviderName() === 'cloud';
    }
}
