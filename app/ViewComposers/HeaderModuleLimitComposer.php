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
                'headerModuleSecondary' => null,
                'competitorModuleLimit' => null,
            ]);

            return;
        }

        $code = ModuleHeaderLimitResolver::resolve();
        if ($code === null) {
            $view->with([
                'headerModuleLimit' => null,
                'headerModuleSecondary' => null,
                'competitorModuleLimit' => null,
            ]);

            return;
        }

        $user = Auth::user();
        $limit = ModuleTariffLimit::forUser($user, $code);

        $secondary = null;
        if ($code === 'SearchSuggestions') {
            $history = ModuleTariffLimit::forUser($user, 'SearchSuggestionsHistory');
            if ($history['applies'] && $history['limit'] !== null) {
                $secondary = [
                    'code' => $history['code'],
                    'label' => (string) __('Search suggestions history label'),
                    'short_label' => (string) __('Search suggestions history label'),
                    'metric' => 'used',
                    'used' => (int) $history['used'],
                    'limit' => (int) $history['limit'],
                    'left' => $history['left'],
                    'exhausted' => $history['exhausted'],
                ];
            }
        }
        if ($code === 'SiteTypes') {
            $history = ModuleTariffLimit::forUser($user, 'SiteTypesHistory');
            if ($history['applies'] && $history['limit'] !== null) {
                $secondary = [
                    'code' => $history['code'],
                    'label' => (string) __('Site types history label'),
                    'short_label' => (string) __('Site types history label'),
                    'metric' => 'used',
                    'used' => (int) $history['used'],
                    'limit' => (int) $history['limit'],
                    'left' => $history['left'],
                    'exhausted' => $history['exhausted'],
                ];
            }
        }
        if ($code === 'PhraseCommerce') {
            $history = ModuleTariffLimit::forUser($user, 'PhraseCommerceHistory');
            if ($history['applies'] && $history['limit'] !== null) {
                $secondary = [
                    'code' => $history['code'],
                    'label' => (string) __('Phrase commerce history label'),
                    'short_label' => (string) __('Phrase commerce history label'),
                    'metric' => 'used',
                    'used' => (int) $history['used'],
                    'limit' => (int) $history['limit'],
                    'left' => $history['left'],
                    'exhausted' => $history['exhausted'],
                ];
            }
        }
        if ($code === 'TextAnalyzer') {
            $uniq = ModuleTariffLimit::forUser($user, 'TextUniqueness');
            if ($uniq['applies'] && $uniq['limit'] !== null) {
                $secondary = [
                    'code' => $uniq['code'],
                    'label' => (string) __('Text uniqueness'),
                    'short_label' => (string) __('Text uniqueness header short'),
                    'metric' => 'left',
                    'used' => (int) $uniq['used'],
                    'limit' => (int) $uniq['limit'],
                    'left' => $uniq['left'],
                    'exhausted' => $uniq['exhausted'],
                ];
            }
        }
        if ($code === 'TextUniqueness') {
            $history = ModuleTariffLimit::forUser($user, 'TextUniquenessHistory');
            if ($history['applies'] && $history['limit'] !== null) {
                $secondary = [
                    'code' => $history['code'],
                    'label' => (string) __('Text uniqueness history label'),
                    'short_label' => (string) __('Text uniqueness history label'),
                    'metric' => 'used',
                    'used' => (int) $history['used'],
                    'limit' => (int) $history['limit'],
                    'left' => $history['left'],
                    'exhausted' => $history['exhausted'],
                ];
            }
        }

        $view->with('headerModuleLimit', $limit);
        $view->with('headerModuleSecondary', $secondary);
        $view->with(
            'competitorModuleLimit',
            $code === self::COMPETITOR_TARIFF_CODE ? $limit : null
        );
    }
}
