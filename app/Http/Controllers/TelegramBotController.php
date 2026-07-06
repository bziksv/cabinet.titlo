<?php

namespace App\Http\Controllers;

use App\Services\TelegramConnectBonusService;
use App\Services\TelegramBotService;
use App\Support\TelegramStartPayload;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class TelegramBotController extends Controller
{
    public function index(Request $request): Response
    {
        $message = $request->json('message') ?? $request->input('message');
        $reply = null;
        $chatId = null;

        if (is_array($message) && !empty($message['chat']['id']) && !empty($message['text'])) {
            $chatId = (int) $message['chat']['id'];
            $reply = $this->buildReplyForMessage($message, $chatId);
        }

        if ($chatId !== null && $reply !== null && $reply !== '') {
            register_shutdown_function(static function () use ($chatId, $reply) {
                try {
                    (new TelegramBotService($chatId))->sendMsg($reply);
                } catch (\Throwable $e) {
                    Log::warning('Telegram webhook reply failed', [
                        'chat_id' => $chatId,
                        'error' => $e->getMessage(),
                    ]);
                }
            });
        }

        return response('ok', 200);
    }

    private function buildReplyForMessage(array $message, int $chatId): ?string
    {
        $text = explode(' ', trim((string) $message['text']), 2);

        if (!isset($text[1])) {
            return __('Telegram connect start hint');
        }

        $email = TelegramStartPayload::decodeEmail($text[1]);
        if ($email === null) {
            return __('Telegram connect start invalid');
        }

        $validator = Validator::make(['email' => $email], [
            'email' => ['required', 'email'],
        ]);

        if (!$validator->passes()) {
            return __('Telegram connect start invalid');
        }

        $result = app(TelegramConnectBonusService::class)
            ->linkUserFromTelegramStart($chatId, $email);

        if (!$result['linked']) {
            Log::warning('Telegram /start: user not found', ['email' => $email, 'chat_id' => $chatId]);

            return __('Telegram connect start user not found');
        }

        if ($result['bonus_granted']) {
            $amount = app(TelegramConnectBonusService::class)->bonusAmount();

            return __('Telegram connect bonus success message', [
                'amount' => number_format($amount, 0, '.', ' '),
            ]);
        }

        return __('You have successfully subscribed to the notification newsletter');
    }
}
