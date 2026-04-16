<?php

namespace App\Console\Commands;

use App\Http\Controllers\GowaBotController;
use App\Models\BotUser;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class TestGowaSync extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:test-gowa-sync
                            {chat? : WhatsApp chat JID to sync (e.g., 79956572287@s.whatsapp.net)}
                            {--all : Sync all local WhatsApp chats}
                            {--limit=10 : Number of messages to fetch per chat}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test GOWA chat history sync locally';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $controller = new GowaBotController();
        $limit = (int) $this->option('limit');

        if ($this->option('all')) {
            return $this->syncAllChats($controller, $limit);
        }

        $chatJid = $this->argument('chat');
        if ($chatJid === null) {
            $this->error('Please provide a chat JID or use --all flag');

            return 1;
        }

        return $this->syncSingleChat($controller, $chatJid, $limit);
    }

    private function syncSingleChat(GowaBotController $controller, string $chatJid, int $limit): int
    {
        $this->info("Testing sync for chat: {$chatJid}");
        $this->info("Fetching last {$limit} messages...");

        Log::info('GOWA manual sync started', ['chat' => $chatJid, 'limit' => $limit]);

        // Call sync directly via reflection since it's protected
        $method = new \ReflectionMethod($controller, 'syncChatHistory');
        $method->setAccessible(true);

        $botUser = BotUser::where('chat_id', $chatJid)
            ->where('platform', 'whatsapp')
            ->first();

        if ($botUser === null) {
            $this->warn("No BotUser found for {$chatJid}, creating test context...");
        }

        $method->invoke($controller, $chatJid, $botUser, $limit);

        $this->info('Sync completed. Check logs for details.');

        return 0;
    }

    private function syncAllChats(GowaBotController $controller, int $limit): int
    {
        $this->info('Syncing all local WhatsApp chats...');

        $botUsers = BotUser::where('platform', 'whatsapp')
            ->whereNotNull('chat_id')
            ->get();

        $this->info("Found {$botUsers->count()} WhatsApp chats");

        $method = new \ReflectionMethod($controller, 'syncChatHistory');
        $method->setAccessible(true);

        $successCount = 0;
        $failCount = 0;

        foreach ($botUsers as $botUser) {
            $chatJid = $botUser->chat_id;
            $this->info("\n--- Syncing {$chatJid} ---");

            try {
                $method->invoke($controller, $chatJid, $botUser, $limit);
                $successCount++;
                $this->info("✓ Sync triggered for {$chatJid}");
            } catch (\Throwable $e) {
                $failCount++;
                $this->error("✗ Failed: {$e->getMessage()}");
            }
        }

        $this->info("\n========================================");
        $this->info("Sync complete: {$successCount} success, {$failCount} failed");
        $this->info('Check laravel.log for detailed results');

        return $failCount > 0 ? 1 : 0;
    }
}
