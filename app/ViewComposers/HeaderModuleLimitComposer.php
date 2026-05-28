<?php

namespace App\ViewComposers;

use App\Support\ModuleHeaderLimitResolver;
use App\Support\ModuleTariffLimit;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class HeaderModuleLimitComposer
{
    public const COMPETITOR_TARIFF_CODE = 'CompetitorAnalysisPhrases';

    public function compose(View $view): void
    {
        if (!Auth::check()) {
            $view->with([
                'headerModuleLimit' => null,
                'competitorModuleLimit' => null,
            ]);

            return;
        }

        $code = ModuleHeaderLimitResolver::resolve();
        if ($code === null) {
            $view->with([
                'headerModuleLimit' => null,
                'competitorModuleLimit' => null,
            ]);

            return;
        }

        $limit = ModuleTariffLimit::forUser(Auth::user(), $code);

        $view->with('headerModuleLimit', $limit);
        $view->with(
            'competitorModuleLimit',
            $code === self::COMPETITOR_TARIFF_CODE ? $limit : null
        );
    }
}
