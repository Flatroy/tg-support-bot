<?php

declare(strict_types=1);

namespace App\Services\WhatsApp;

use App\Contracts\WhatsApp\WhatsAppProviderInterface;
use App\Services\WhatsApp\Providers\CloudApiProvider;
use App\Services\WhatsApp\Providers\WahaProvider;

class WhatsAppProviderFactory
{
    public static function make(): WhatsAppProviderInterface
    {
        $provider = config('traffic_source.settings.whatsapp.provider', 'cloud');

        return match ($provider) {
            'waha' => new WahaProvider(),
            default => new CloudApiProvider(),
        };
    }

    public static function getProviderName(): string
    {
        return (string) config('traffic_source.settings.whatsapp.provider', 'cloud');
    }

    public static function isWaha(): bool
    {
        return self::getProviderName() === 'waha';
    }

    public static function isCloudApi(): bool
    {
        return self::getProviderName() === 'cloud';
    }
}
