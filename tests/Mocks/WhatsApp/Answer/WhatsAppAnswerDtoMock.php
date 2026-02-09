<?php

namespace Tests\Mocks\WhatsApp\Answer;

use App\DTOs\WhatsApp\WhatsAppAnswerDto;

class WhatsAppAnswerDtoMock
{
    /**
     * @return array
     */
    public static function getDtoParams(): array
    {
        return [
            'response_code' => 200,
            'messaging_product' => 'whatsapp',
            'contacts' => [
                ['input' => '15559876543', 'wa_id' => '15559876543'],
            ],
            'messages' => [
                ['id' => 'wamid.' . time()],
            ],
        ];
    }

    /**
     * @param array $dtoParams
     *
     * @return WhatsAppAnswerDto
     */
    public static function getDto(array $dtoParams = []): WhatsAppAnswerDto
    {
        if (empty($dtoParams)) {
            $dtoParams = self::getDtoParams();
        }

        return WhatsAppAnswerDto::fromData($dtoParams);
    }
}
