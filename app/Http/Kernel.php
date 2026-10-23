<?php

namespace App\Http;

use App\Http\Middleware\ApplyMonitoringTeamPermissions;
use App\Http\Middleware\Authenticate;
use App\Http\Middleware\CheckForMaintenanceMode;
use App\Http\Middleware\CheckHttpHeadersDataBase;
use App\Http\Middleware\DeleteTariffByUsers;
use App\Http\Middleware\EncryptCookies;
use App\Http\Middleware\LastOnline;
use App\Http\Middleware\RedirectIfAuthenticated;
use App\Http\Middleware\RoleMiddleware;
use App\Http\Middleware\EnsureTeamPermissionMiddleware;
use App\Http\Middleware\SetTeamContext;
use App\Http\Middleware\VisitStatistics;
use \Spatie\Permission\Middlewares\PermissionMiddleware;
use \Spatie\Permission\Middlewares\RoleOrPermissionMiddleware;
use App\Http\Middleware\SetLocaleToAuthUser;
use App\Http\Middleware\TrimStrings;
use App\Http\Middleware\TrustProxies;
use App\Http\Middleware\VerifyCsrfToken;
use Illuminate\Auth\Middleware\AuthenticateWithBasicAuth;
use Illuminate\Auth\Middleware\Authorize;
use App\Http\Middleware\EnsureEmailIsVerified;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Foundation\Http\Kernel as HttpKernel;
use Illuminate\Foundation\Http\Middleware\ConvertEmptyStringsToNull;
use Illuminate\Foundation\Http\Middleware\ValidatePostSize;
use Illuminate\Http\Middleware\SetCacheHeaders;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Routing\Middleware\ValidateSignature;
use Illuminate\Session\Middleware\AuthenticateSession;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class Kernel extends HttpKernel
{
    /**
     * @var array
     */
    protected $bootstrappers = [
        \Illuminate\Foundation\Bootstrap\LoadEnvironmentVariables::class,
        \Illuminate\Foundation\Bootstrap\LoadConfiguration::class,
        \App\Bootstrap\HandleExceptions::class,
        \Illuminate\Foundation\Bootstrap\RegisterFacades::class,
        \Illuminate\Foundation\Bootstrap\RegisterProviders::class,
        \Illuminate\Foundation\Bootstrap\BootProviders::class,
    ];

    /**
     * The application's global HTTP middleware stack.
     *
     * These middleware are run during every request to your application.
     *
     * @var array
     */
    protected $middleware = [
        \App\Http\Middleware\ServeOutagePage::class,
        TrustProxies::class,
        CheckForMaintenanceMode::class,
        ValidatePostSize::class,
        TrimStrings::class,
        ConvertEmptyStringsToNull::class,
    ];

    /**
     * The application's route middleware groups.
     *
     * @var array
     */
    protected $middlewareGroups = [
        'web' => [
            EncryptCookies::class,
            AddQueuedCookiesToResponse::class,
            StartSession::class,
            // \Illuminate\Session\Middleware\AuthenticateSession::class,
            ShareErrorsFromSession::class,
            VerifyCsrfToken::class,
            SetTeamContext::class,
            SubstituteBindings::class,
            CheckHttpHeadersDataBase::class,
            SetLocaleToAuthUser::class,
            DeleteTariffByUsers::class,
            LastOnline::class,
            VisitStatistics::class,
            \App\Http\Middleware\DemoCabinetReadonly::class,
        ],

        'api' => [
            // Shop / AI poll + bulk phrase: 60/min слишком мало (Too Many Attempts).
            'throttle:300,1',
            'bindings',
        ],
    ];

    /**
     * The application's route middleware.
     *
     * These middleware may be assigned to groups or used individually.
     *
     * @var array
     */
    protected $routeMiddleware = [
        'auth' => Authenticate::class,
        'auth.basic' => AuthenticateWithBasicAuth::class,
        'bindings' => SubstituteBindings::class,
        'cache.headers' => SetCacheHeaders::class,
        'can' => Authorize::class,
        'guest' => RedirectIfAuthenticated::class,
        'signed' => ValidateSignature::class,
        'throttle' => ThrottleRequests::class,
        'verified' => EnsureEmailIsVerified::class,
        'role' => RoleMiddleware::class,
        'permission' => EnsureTeamPermissionMiddleware::class,
        'role_or_permission' => RoleOrPermissionMiddleware::class,
        'integration.api' => \App\Http\Middleware\AuthenticateIntegrationApiKey::class,
        'integration.api.throttle' => \App\Http\Middleware\ThrottleIntegrationApiKey::class,
        'ai.generation.ui.admin' => \App\Http\Middleware\EnsureAiGenerationUiAdmin::class,
    ];

    /**
     * The priority-sorted list of middleware.
     *
     * This forces non-global middleware to always be in the given order.
     *
     * @var array
     */
    protected $middlewarePriority = [
        StartSession::class,
        ShareErrorsFromSession::class,
        Authenticate::class,
        AuthenticateSession::class,
        SubstituteBindings::class,
        Authorize::class,
    ];
}
