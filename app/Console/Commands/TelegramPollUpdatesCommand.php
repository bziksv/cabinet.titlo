<?php

namespace App\Console\Commands;

use App\Services\TelegramBotService;
use App\Services\TelegramUpdateHandler;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class TelegramPollUpdatesCommand extends Command
{
    protected $signature = 'telegram:poll-updates';

    protected $description = 'Poll Telegram getUpdates (when inbound webhook is blocked)';

    public function handle(TelegramUpdateHandler $handler): int
    {
        if (!config('cabinet-telegram.poll_updates', true)) {
            return 0;
        }

        $offset = (int) Cache::get('telegram_bot_update_offset', 0);
        $result = TelegramBotService::fetchUpdates($offset);

        if (!$result['ok']) {
            Log::warning('Telegram getUpdates failed', ['error' => $result['error'] ?? 'unknown']);

            return 1;
        }

        $nextOffset = $offset;
        $processed = 0;

        foreach ($result['updates'] as $update) {
            if (!isset($update['update_id'])) {
                continue;
            }

            $updateId = (int) $update['update_id'];
            if ($updateId >= $nextOffset) {
                $nextOffset = $updateId + 1;
            }

            if (!isset($update['message']) || !is_array($update['message'])) {
                continue;
            }

            $chatId = (int) ($update['message']['chat']['id'] ?? 0);
            $reply = $handler->handleMessage($update['message']);

            if ($chatId > 0 && $reply !== null && $reply !== '') {
                $sent = (new TelegramBotService($chatId))->sendMsg($reply);
                if (!$sent) {
                    Log::warning('Telegram poll reply failed', [
                        'chat_id' => $chatId,
                        'error' => TelegramBotService::$lastError,
                    ]);
                }
            }

            $processed++;
        }

        if ($nextOffset > $offset) {
            Cache::forever('telegram_bot_update_offset', $nextOffset);
        }

        if ($processed > 0) {
            Log::info('Telegram poll processed updates', [
                'count' => $processed,
                'next_offset' => $nextOffset,
            ]);
        }

        return 0;
    }
}
