<?php

namespace App\ViewComposers;

use App\Services\TelegramConnectBonusService;
use App\Support\DemoCabinet;
use App\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class TelegramConnectPromptComposer
{
    public function compose(View $view): void
    {
        $user = Auth::user();

        if (!$user instanceof User || DemoCabinet::isDemoUser($user)) {
            $view->with([
                'showTelegramConnectPrompt' => false,
                'telegramBotSubscribeUrl' => null,
                'telegramConnectBonusAmount' => 0,
                'telegramConnectBonusEligible' => false,
            ]);

            return;
        }

        $view->with([
            'showTelegramConnectPrompt' => $user->shouldShowTelegramConnectPrompt(),
            'telegramBotSubscribeUrl' => $user->telegramBotSubscribeUrl(),
            'telegramConnectBonusAmount' => app(TelegramConnectBonusService::class)->bonusAmount(),
            'telegramConnectBonusEligible' => app(TelegramConnectBonusService::class)->userEligibleForBonus($user),
        ]);
    }
}
