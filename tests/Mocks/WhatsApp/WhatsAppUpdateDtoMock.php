<?php

namespace Tests\Mocks\WhatsApp;

use App\DTOs\WhatsApp\WhatsAppUpdateDto;
use Illuminate\Support\Facades\Request;

class WhatsAppUpdateDtoMock
{
    /**
     * @return array
     */
    public static function getDtoParams(): array
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
                                        'id' => 'wamid.' . time(),
                                        'timestamp' => (string) time(),
                                        'text' => ['body' => 'Test text'],
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

    /**
     * @param array $dtoParams
     *
     * @return WhatsAppUpdateDto|null
     */
    public static function getDto(array $dtoParams = []): ?WhatsAppUpdateDto
    {
        if (empty($dtoParams)) {
            $dtoParams = self::getDtoParams();
        }

        $request = Request::create('api/whatsapp/bot', 'POST', $dtoParams);

        return WhatsAppUpdateDto::fromRequest($request);
    }

    /**
     * @return array
     */
    public static function getImageDtoParams(): array
    {
        $params = self::getDtoParams();
        $params['entry'][0]['changes'][0]['value']['messages'][0] = [
            'from' => '15559876543',
            'id' => 'wamid.' . time(),
            'timestamp' => (string) time(),
            'type' => 'image',
            'image' => [
                'id' => 'media_id_123',
                'mime_type' => 'image/jpeg',
                'caption' => 'Test caption',
            ],
        ];

        return $params;
    }

    /**
     * @return array
     */
    public static function getLocationDtoParams(): array
    {
        $params = self::getDtoParams();
        $params['entry'][0]['changes'][0]['value']['messages'][0] = [
            'from' => '15559876543',
            'id' => 'wamid.' . time(),
            'timestamp' => (string) time(),
            'type' => 'location',
            'location' => [
                'latitude' => 55.7558,
                'longitude' => 37.6173,
            ],
        ];

        return $params;
    }
}
