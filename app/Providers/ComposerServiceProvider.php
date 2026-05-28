<?php

namespace App\Providers;


use App\ViewComposers\AdminSettingsMenuComposer;
use App\ViewComposers\CountUnreadNewsComposer;
use App\ViewComposers\DescriptionComposer;
use App\ViewComposers\HeaderModuleLimitComposer;
use App\ViewComposers\LimitsComposer;
use App\ViewComposers\MenuComposer;
use App\ViewComposers\StatisticsComposer;
use App\ViewComposers\FeatureIdeaBadgeComposer;
use App\ViewComposers\SupportInboxBadgeComposer;
use App\ViewComposers\TelegramConnectPromptComposer;
use App\ViewComposers\UserPanelComposer;
use Illuminate\Support\ServiceProvider;

class ComposerServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     *
     * @return void
     */
    public function register()
    {
        //
    }

    /**
     * Bootstrap services.
     *
     * @return void
     */
    public function boot()
    {
        
        view()->composer('component.card', DescriptionComposer::class);
        view()->composer('users.panel', UserPanelComposer::class);
        view()->composer('users.panel', AdminSettingsMenuComposer::class);
        view()->composer('layouts.partials.app-header', UserPanelComposer::class);
        view()->composer('layouts.partials.app-header', LimitsComposer::class);
        view()->composer('layouts.partials.app-header', HeaderModuleLimitComposer::class);
        view()->composer(['competitors.index', 'competitors.config'], HeaderModuleLimitComposer::class);
        view()->composer('layouts.partials.app-header', CountUnreadNewsComposer::class);
        view()->composer('layouts.partials.app-header', SupportInboxBadgeComposer::class);
        view()->composer('layouts.partials.app-header', FeatureIdeaBadgeComposer::class);
        view()->composer('navigation.sidebar', MenuComposer::class);
        view()->composer('layouts.app', StatisticsComposer::class);
        view()->composer('layouts.app', TelegramConnectPromptComposer::class);
    }
}
