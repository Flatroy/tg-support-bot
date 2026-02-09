<?php

namespace App\Services\TgWhatsApp;

use App\DTOs\TelegramUpdateDto;
use App\DTOs\WhatsApp\WhatsAppTextMessageDto;
use App\Jobs\SendMessage\SendWhatsAppMessageJob;
use App\Logging\LokiLogger;
use App\Models\Message;
use App\Services\ActionService\Edit\FromTgEditService;

class TgWhatsAppEditService extends FromTgEditService
{
    public function __construct(TelegramUpdateDto $update)
    {
        parent::__construct($update);
    }

    /**
     * WhatsApp does not support editing sent messages.
     * When a manager edits a message in the TG group, we send a new "corrected" message to WhatsApp.
     *
     * @return void
     */
    public function handleUpdate(): void
    {
        try {
            if ($this->update->typeQuery !== 'edited_message') {
                throw new \Exception("Unknown event type: {$this->update->typeQuery}", 1);
            }

            if (!empty($this->update->rawData['edited_message']['photo']) || !empty($this->update->rawData['edited_message']['document'])) {
                $this->editMessageCaption();
            } else {
                $this->editMessageText();
            }

            echo 'ok';
        } catch (\Throwable $e) {
            (new LokiLogger())->logException($e);
        }
    }

    /**
     * @return void
     */
    protected function editMessageText(): void
    {
        $correctedText = '✏️ ' . $this->update->text;

        $queryParams = WhatsAppTextMessageDto::from([
            'to' => (string) $this->botUser->chat_id,
            'type' => 'text',
            'text' => $correctedText,
        ]);

        SendWhatsAppMessageJob::dispatch(
            $this->botUser->id,
            $this->update,
            $queryParams,
        );
    }

    /**
     * @return void
     */
    protected function editMessageCaption(): void
    {
        $this->editMessageText();
    }
}
