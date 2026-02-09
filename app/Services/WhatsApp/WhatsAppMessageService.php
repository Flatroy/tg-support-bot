<?php

declare(strict_types=1);

namespace App\Services\WhatsApp;

use App\DTOs\TGTextMessageDto;
use App\DTOs\WhatsApp\WhatsAppUpdateDto;
use App\Jobs\SendMessage\SendWhatsAppTelegramMessageJob;
use App\Logging\LokiLogger;
use App\Models\BotUser;
use App\Services\ActionService\Send\ToTgMessageService;
use App\WhatsAppBot\WhatsAppMethods;

class WhatsAppMessageService extends ToTgMessageService
{
    protected string $source = 'whatsapp';

    protected string $typeMessage = 'incoming';

    protected mixed $update;

    protected ?BotUser $botUser;

    protected TGTextMessageDto $messageParamsDTO;

    public function __construct(WhatsAppUpdateDto $update)
    {
        parent::__construct($update);
    }

    /**
     * @return void
     */
    public function handleUpdate(): void
    {
        try {
            match ($this->update->type) {
                'text' => $this->sendMessage(),
                'image' => $this->sendPhoto(),
                'document' => $this->sendDocument(),
                'location' => $this->sendLocation(),
                'audio' => $this->sendVoice(),
                'contacts' => $this->sendContact(),
                'sticker' => $this->sendSticker(),
                'video' => $this->sendDocument(),
                default => null,
            };
        } catch (\Throwable $e) {
            (new LokiLogger())->logException($e);
        }
    }

    /**
     * @return void
     */
    protected function sendMessage(): void
    {
        $this->messageParamsDTO->text = $this->update->text;

        SendWhatsAppTelegramMessageJob::dispatch(
            $this->botUser->id,
            $this->update,
            $this->messageParamsDTO,
        );
    }

    /**
     * @return void
     */
    protected function sendPhoto(): void
    {
        $localPath = $this->downloadWhatsAppMedia($this->update->mediaId);
        if (empty($localPath)) {
            return;
        }

        $this->messageParamsDTO->methodQuery = 'sendPhoto';
        $this->messageParamsDTO->uploaded_file_path = $localPath;
        $this->messageParamsDTO->caption = $this->update->caption ?? '';

        SendWhatsAppTelegramMessageJob::dispatch(
            $this->botUser->id,
            $this->update,
            $this->messageParamsDTO,
        );
    }

    /**
     * @return void
     */
    protected function sendDocument(): void
    {
        $localPath = $this->downloadWhatsAppMedia($this->update->mediaId, $this->update->filename);
        if (empty($localPath)) {
            return;
        }

        $this->messageParamsDTO->methodQuery = 'sendDocument';
        $this->messageParamsDTO->uploaded_file_path = $localPath;
        $this->messageParamsDTO->caption = $this->update->caption ?? '';

        SendWhatsAppTelegramMessageJob::dispatch(
            $this->botUser->id,
            $this->update,
            $this->messageParamsDTO,
        );
    }

    /**
     * @return void
     */
    protected function sendLocation(): void
    {
        $this->messageParamsDTO->methodQuery = 'sendLocation';
        $this->messageParamsDTO->latitude = $this->update->location['latitude'];
        $this->messageParamsDTO->longitude = $this->update->location['longitude'];

        SendWhatsAppTelegramMessageJob::dispatch(
            $this->botUser->id,
            $this->update,
            $this->messageParamsDTO,
        );
    }

    /**
     * @return void
     */
    protected function sendVoice(): void
    {
        $localPath = $this->downloadWhatsAppMedia($this->update->mediaId);
        if (empty($localPath)) {
            return;
        }

        $this->messageParamsDTO->methodQuery = 'sendVoice';
        $this->messageParamsDTO->uploaded_file_path = $localPath;

        SendWhatsAppTelegramMessageJob::dispatch(
            $this->botUser->id,
            $this->update,
            $this->messageParamsDTO,
        );
    }

    /**
     * @return void
     */
    protected function sendSticker(): void
    {
        $localPath = $this->downloadWhatsAppMedia($this->update->mediaId);
        if (empty($localPath)) {
            return;
        }

        $this->messageParamsDTO->methodQuery = 'sendSticker';
        $this->messageParamsDTO->uploaded_file_path = $localPath;

        SendWhatsAppTelegramMessageJob::dispatch(
            $this->botUser->id,
            $this->update,
            $this->messageParamsDTO,
        );
    }

    /**
     * @return void
     */
    protected function sendContact(): void
    {
        $contacts = $this->update->contacts;
        if (empty($contacts)) {
            return;
        }

        $contact = $contacts[0];
        $textMessage = "Контакт: \n";
        $textMessage .= "Имя: {$contact['name']['formatted_name']}\n";
        if (!empty($contact['phones'][0]['phone'])) {
            $textMessage .= "Телефон: {$contact['phones'][0]['phone']}\n";
        }

        $this->messageParamsDTO->text = $textMessage;

        SendWhatsAppTelegramMessageJob::dispatch(
            $this->botUser->id,
            $this->update,
            $this->messageParamsDTO,
        );
    }

    /**
     * @return void
     */
    protected function sendVideoNote(): void
    {
        //
    }

    /**
     * Download media from WhatsApp and return the local file path.
     *
     * @param string|null $mediaId
     *
     * @return string|null
     */
    private function downloadWhatsAppMedia(?string $mediaId, ?string $filename = null): ?string
    {
        if (empty($mediaId)) {
            return null;
        }

        $mediaUrl = WhatsAppMethods::getMediaUrl($mediaId);
        if (empty($mediaUrl)) {
            return null;
        }

        return WhatsAppMethods::downloadMedia($mediaUrl, $filename);
    }
}
