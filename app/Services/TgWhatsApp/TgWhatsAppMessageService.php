<?php

declare(strict_types=1);

namespace App\Services\TgWhatsApp;

use App\DTOs\TelegramUpdateDto;
use App\DTOs\WhatsApp\WhatsAppTextMessageDto;
use App\Helpers\TelegramHelper;
use App\Jobs\SendMessage\SendWhatsAppMessageJob;
use App\Services\ActionService\Send\FromTgMessageService;
use App\Services\Button\ButtonParser;
use App\WhatsAppBot\WhatsAppMethods;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TgWhatsAppMessageService extends FromTgMessageService
{
    public function __construct(TelegramUpdateDto $update)
    {
        parent::__construct($update);
    }

    /**
     * @return void
     */
    public function handleUpdate(): void
    {
        try {
            if ($this->update->typeQuery !== 'message') {
                throw new \Exception("Unknown event type: {$this->update->typeQuery}", 1);
            }

            if (!empty($this->update->rawData['message']['photo'])) {
                $this->sendPhoto();
            } elseif (!empty($this->update->rawData['message']['document'])) {
                $this->sendDocument();
            } elseif (!empty($this->update->rawData['message']['location'])) {
                $this->sendLocation();
            } elseif (!empty($this->update->rawData['message']['voice'])) {
                $this->sendVoice();
            } elseif (!empty($this->update->rawData['message']['sticker'])) {
                $this->sendSticker();
            } elseif (!empty($this->update->rawData['message']['contact'])) {
                $this->sendContact();
            } elseif (!empty($this->update->text)) {
                $this->sendMessage();
            }

            echo 'ok';
        } catch (\Throwable $exception) {
            Log::channel('loki')->log($exception->getCode() === 1 ? 'warning' : 'error', $exception->getMessage(), ['file' => $exception->getFile(), 'line' => $exception->getLine()]);
        }
    }

    /**
     * @return void
     */
    protected function sendPhoto(): void
    {
        $fileUrl = TelegramHelper::getFileTelegramPath($this->update->fileId);
        if (empty($fileUrl)) {
            return;
        }

        $localPath = $this->downloadTelegramFile($fileUrl, 'jpg');
        if (empty($localPath)) {
            return;
        }

        $mediaId = WhatsAppMethods::uploadMedia($localPath, 'image/jpeg');
        @unlink($localPath);

        if (empty($mediaId)) {
            return;
        }

        $queryParams = WhatsAppTextMessageDto::from([
            'to' => (string) $this->botUser->chat_id,
            'type' => 'image',
            'mediaId' => $mediaId,
            'caption' => $this->update->caption ?? '',
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
    protected function sendDocument(): void
    {
        $fileUrl = TelegramHelper::getFileTelegramPath($this->update->fileId);
        if (empty($fileUrl)) {
            return;
        }

        $filename = $this->update->rawData['message']['document']['file_name'] ?? null;

        $queryParams = WhatsAppTextMessageDto::from([
            'to' => (string) $this->botUser->chat_id,
            'type' => 'document',
            'mediaUrl' => $fileUrl,
            'filename' => $filename,
            'caption' => $this->update->caption ?? '',
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
    protected function sendLocation(): void
    {
        $queryParams = WhatsAppTextMessageDto::from([
            'to' => (string) $this->botUser->chat_id,
            'type' => 'location',
            'latitude' => $this->update->location['latitude'],
            'longitude' => $this->update->location['longitude'],
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
    protected function sendVoice(): void
    {
        $fileUrl = TelegramHelper::getFileTelegramPath($this->update->fileId);
        if (empty($fileUrl)) {
            return;
        }

        $localPath = $this->downloadTelegramFile($fileUrl, 'ogg');
        if (empty($localPath)) {
            return;
        }

        $mediaId = WhatsAppMethods::uploadMedia($localPath, 'audio/ogg');
        @unlink($localPath);

        if (empty($mediaId)) {
            return;
        }

        $queryParams = WhatsAppTextMessageDto::from([
            'to' => (string) $this->botUser->chat_id,
            'type' => 'audio',
            'mediaId' => $mediaId,
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
    protected function sendSticker(): void
    {
        $emoji = $this->update->rawData['message']['sticker']['emoji'] ?? '🙂';

        $queryParams = WhatsAppTextMessageDto::from([
            'to' => (string) $this->botUser->chat_id,
            'type' => 'text',
            'text' => $emoji,
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
    protected function sendVideoNote(): void
    {
        //
    }

    /**
     * @return void
     */
    protected function sendContact(): void
    {
        $contactData = $this->update->rawData['message']['contact'];

        $textMessage = "Контакт: \n";
        $textMessage .= "Имя: {$contactData['first_name']}\n";
        if (!empty($contactData['phone_number'])) {
            $textMessage .= "Телефон: {$contactData['phone_number']}\n";
        }

        $queryParams = WhatsAppTextMessageDto::from([
            'to' => (string) $this->botUser->chat_id,
            'type' => 'text',
            'text' => $textMessage,
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
    protected function sendMessage(): void
    {
        $buttonParser = new ButtonParser();
        $parsedMessage = $buttonParser->parse($this->update->text);

        $queryParams = WhatsAppTextMessageDto::from([
            'to' => (string) $this->botUser->chat_id,
            'type' => 'text',
            'text' => $parsedMessage->text,
        ]);

        SendWhatsAppMessageJob::dispatch(
            $this->botUser->id,
            $this->update,
            $queryParams,
        );
    }

    /**
     * Download a file from a URL to a local temp path.
     *
     * @param string $url
     * @param string $extension
     *
     * @return string|null
     */
    private function downloadTelegramFile(string $url, string $extension): ?string
    {
        try {
            $response = Http::get($url);
            if (!$response->successful()) {
                return null;
            }

            $tempPath = sys_get_temp_dir() . '/' . uniqid('tg_media_', true) . '.' . $extension;
            file_put_contents($tempPath, $response->body());

            return $tempPath;
        } catch (\Throwable $exception) {
            Log::channel('loki')->log($exception->getCode() === 1 ? 'warning' : 'error', $exception->getMessage(), ['file' => $exception->getFile(), 'line' => $exception->getLine()]);

            return null;
        }
    }
}
