<?php

namespace App\DTOs\WhatsApp;

readonly class WhatsAppAnswerDto
{
    public function __construct(
        public int $response_code,
        public ?string $message_id,
        public ?string $error_message,
        public ?string $error_type,
        public array $rawData,
    ) {
    }

    /**
     * @param array $dataAnswer
     *
     * @return self
     */
    public static function fromData(array $dataAnswer): self
    {
        $messageId = null;
        if (!empty($dataAnswer['messages'][0]['id'])) {
            $messageId = $dataAnswer['messages'][0]['id'];
        }

        $errorMessage = null;
        $errorType = null;
        if (!empty($dataAnswer['error'])) {
            $errorMessage = $dataAnswer['error']['message'] ?? 'Unknown error';
            $errorType = $dataAnswer['error']['type'] ?? null;
        }

        return new self(
            response_code: $dataAnswer['response_code'] ?? 200,
            message_id: $messageId,
            error_message: $errorMessage,
            error_type: $errorType,
            rawData: $dataAnswer,
        );
    }
}
