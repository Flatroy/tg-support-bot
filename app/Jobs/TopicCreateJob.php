<?php

namespace App\Jobs;

use App\Actions\Telegram\GetChat;
use App\Actions\Telegram\SendContactMessage;
use App\Models\BotUser;
use App\Models\ExternalUser;
use App\Models\WhatsappMessage;
use App\TelegramBot\TelegramMethods;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class TopicCreateJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public array $backoff = [60, 180, 300];

    private BotUser $botUser;

    private TelegramMethods $telegramMethods;

    private int $botUserId;

    private ?string $senderName;

    public function __construct(
        int $botUserId,
        TelegramMethods $telegramMethods = null,
        ?string $senderName = null,
    ) {
        $this->botUserId = $botUserId;
        $this->senderName = $senderName;

        $this->telegramMethods = $telegramMethods ?? new TelegramMethods();
    }

    /**
     * @return void
     */
    public function handle(): void
    {
        try {
            $this->botUser = BotUser::find($this->botUserId);

            $topicName = $this->generateNameTopic($this->botUser);

            $response = $this->telegramMethods->sendQueryTelegram('createForumTopic', [
                'chat_id' => config('traffic_source.settings.telegram.group_id'),
                'name' => $topicName,
                'icon_custom_emoji_id' => __('icons.incoming'),
            ]);

            if ($response->ok === true) {
                $this->botUser->topic_id = $response->message_thread_id;
                $this->botUser->save();

                (new SendContactMessage())->execute($this->botUser);
                return;
            }

            if ($response->response_code === 429) {
                $retryAfter = $response->parameters->retry_after ?? 3;
                Log::warning("429 Too Many Requests. Retry after {$retryAfter} sec.");
                $this->release($retryAfter);
                return;
            }

            Log::error('TopicCreateJob: unknown error', [
                'response' => (array)$response,
            ]);
        } catch (\Throwable $e) {
            Log::channel('loki')->log($e->getCode() === 1 ? 'warning' : 'error', $e->getMessage(), ['file' => $e->getFile(), 'line' => $e->getLine()]);
        }
    }

    /**
     * Generate chat name.
     *
     * @param BotUser $botUser
     *
     * @return string
     */
    protected function generateNameTopic(BotUser $botUser): string
    {
        try {
            if ($botUser->platform === 'external_source') {
                $source = ExternalUser::getSourceById($botUser->chat_id);
                return "#{$botUser->chat_id} ({$source})";
            }

            if ($botUser->platform === 'whatsapp') {
                return $this->generateWhatsAppTopicName($botUser);
            }

            $templateTopicName = config('traffic_source.settings.telegram.template_topic_name');
            if (empty($templateTopicName)) {
                throw new Exception('Template not found');
            }

            if (preg_match('/(\{platform})/', $templateTopicName)) {
                $templateTopicName = str_replace('{platform}', $botUser->platform, $templateTopicName);
            }

            $nameParts = $this->getPartsGenerateName($botUser->chat_id);
            if (empty($nameParts)) {
                throw new Exception('Name parts not found');
            }

            // parsing template
            preg_match_all('/{([^}]+)}/', $templateTopicName, $matches);
            if (empty($matches[1])) {
                throw new Exception('Params template topic name not found');
            }

            $paramsParts = array_combine($matches[0], $matches[1]);

            $topicName = $templateTopicName;
            foreach ($paramsParts as $key => $param) {
                if (empty($nameParts[$param])) {
                    throw new Exception('Params template topic name not found');
                }
                $topicName = str_replace($key, $nameParts[$param], $topicName);
            }

            return $topicName;
        } catch (\Throwable $e) {
            return '#' . $botUser->chat_id . ' (' . $botUser->platform . ')';
        }
    }

    private function generateWhatsAppTopicName(BotUser $botUser): string
    {
        $chatId = (string) $botUser->chat_id;

        $name = $this->senderName ?? $this->getWhatsAppSenderName($botUser);

        // Group chat IDs from WhatsApp are very long numbers (15+ digits)
        // Individual phone numbers are typically 10-15 digits
        $isGroup = strlen($chatId) > 15;

        if ($isGroup) {
            return $name !== null && $name !== ''
                ? $name . ' (whatsapp)'
                : 'Group (whatsapp)';
        }

        return $name !== null && $name !== ''
            ? $name . ' +' . $chatId . ' (whatsapp)'
            : '+' . $chatId . ' (whatsapp)';
    }

    private function getWhatsAppSenderName(BotUser $botUser): ?string
    {
        $waMessage = WhatsappMessage::query()
            ->whereHas('message', fn ($q) => $q->where('bot_user_id', $botUser->id))
            ->whereNotNull('sender_name')
            ->latest()
            ->first();

        if ($waMessage !== null) {
            return $waMessage->sender_name;
        }

        // Fallback: group name seed record stored on group.joined event
        $seed = WhatsappMessage::query()
            ->where('wa_message_id', 'group_joined_' . $botUser->chat_id)
            ->whereNotNull('sender_name')
            ->latest()
            ->first();

        return $seed?->sender_name;
    }

    /**
     * Get parts for chat name generation.
     *
     * @param int $chatId
     *
     * @return array
     *
     * @throws Exception
     */
    protected function getPartsGenerateName(int $chatId): array
    {
        try {
            $chatDataQuery = GetChat::execute($chatId);
            if (!$chatDataQuery->ok) {
                throw new Exception('ChatData not found');
            }

            $chatData = $chatDataQuery->rawData['result'];
            if (empty($chatData)) {
                throw new Exception('ChatData not found');
            }

            $neededKeys = [
                'id',
                'email',
                'first_name',
                'last_name',
                'username',
            ];
            return array_intersect_key($chatData, array_flip($neededKeys));
        } catch (Exception $e) {
            return [];
        }
    }
}
