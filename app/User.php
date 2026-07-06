<?php

namespace App;

use App\Classes\Tariffs\Facades\Tariffs;
use App\Mail\VerifyEmail;
use App\Notifications\BrokenDomainNotification;
use App\Notifications\BrokenLinkNotification;
use App\Notifications\DomainInformationNotification;
use App\Notifications\MonitoringLimitExhaustedNotification;
use App\Notifications\RegisterPasswordEmail;
use App\Notifications\RepairDomainNotification;
use App\Notifications\sendNotificationAboutChangeDNS;
use App\Notifications\sendNotificationAboutExpirationRegistrationPeriod;
use App\Support\NotificationDispatchLogger;
use App\Support\NotificationLocale;
use Carbon\Carbon;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Spatie\Permission\PermissionRegistrar;
use Spatie\Permission\Traits\HasRoles;
use Spatie\Url\Url as SpatieUrl;

class User extends Authenticatable implements MustVerifyEmail
{
    use Notifiable, HasRoles;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'balance', 'name', 'last_name', 'email', 'lang', 'password', 'last_authorization', 'telegram_token', 'telegram_prompt_snoozed_until', 'monitoring_schedule_prompt_snoozed_until', 'metrics', 'statistic'
    ];

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
    protected $hidden = [
        'password', 'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'last_online_at' => 'datetime',
        'telegram_prompt_snoozed_until' => 'datetime',
        'telegram_connect_bonus_at' => 'datetime',
        'monitoring_schedule_prompt_snoozed_until' => 'datetime',
        'news_comments_blocked_at' => 'datetime',
        'metrics' => 'json',
    ];

    /**
     * Delete no verify users
     * @var int
     */
    protected $delete = 30;

    public function getImageAttribute($value)
    {
        if (! $value) {
            return asset('img/user-icon.svg');
        }

        return cabinet_storage_url($value) ?? asset('img/user-icon.svg');
    }

    /**
     * Send the email verification notification.
     *
     * @return void
     */
    public function sendEmailVerificationNotification()
    {
        $user = User::latest()->first();
        $verificationUrl = $this->verificationUrl($user);
        $verificationCode = $this->verificationCode($verificationUrl);

        Mail::to($user->email)->send(new VerifyEmail($user, $verificationUrl, $verificationCode));
        NotificationDispatchLogger::log('auth-verify-email', NotificationDispatchLogger::CHANNEL_EMAIL, (int) $this->id);
    }

    private function verificationUrl($notifiable): string
    {
        return URL::temporarySignedRoute(
            'verification.verify',
            \Illuminate\Support\Carbon::now()->addMinutes(Config::get('auth.verification.expire', 60)),
            ['id' => $notifiable->getKey(), 'hash' => sha1($notifiable->getEmailForVerification())]
        );
    }

    private function verificationCode($code)
    {
        $code = SpatieUrl::fromString($code);
        $code = $code->getQueryParameter('expires');
        session(['verificationCode' => $code]);

        return $code;
    }

    /**
     * Send the password reset notification.
     *
     * @return void
     */
    public function sendProfilePasswordResetNotification($request, $user)
    {
        $this->notify(new RegisterPasswordEmail($request, $user));
    }

    /**
     * @param $request
     * @param $link
     */
    public function sendBrokenLinkNotification($request, $link)
    {
        $this->sendBrokenLinkAlerts($request, $link);
    }

    /**
     * Оповещения по проблемной ссылке: Telegram (все тарифы при подключённом боте), email — платные.
     */
    public function sendBrokenLinkAlerts($error, $link, ?ProjectTracking $project = null): void
    {
        if ($project && !(bool) $project->notify_email) {
            return;
        }

        if (!BacklinkConfig::emailEnabled() || !$this->canReceiveBacklinkEmail()) {
            return;
        }

        $this->notify(new BrokenLinkNotification($error, $link));
    }

    /**
     * Telegram: одно сообщение на проект (сводка по числу проблемных ссылок).
     */
    public function sendBrokenLinkProjectTelegram(ProjectTracking $project, int $problemCount, bool $isTest = false): bool
    {
        if (!(bool) $project->notify_telegram && !$isTest) {
            return false;
        }

        if (!BacklinkConfig::telegramEnabled() || !$this->isTelegramConnected()) {
            return false;
        }

        NotificationLocale::apply($this);

        return TelegramBot::brokenLinkProjectNotification($project, $this->chat_id, $problemCount, $isTest);
    }

    public function receivesBacklinkExternalAlerts(): bool
    {
        if (BacklinkConfig::telegramEnabled() && $this->isTelegramConnected()) {
            return true;
        }

        return $this->canReceiveBacklinkEmail();
    }

    /**
     * Есть роль платного тарифа (Optimal / Ultimate / Maximum).
     */
    public function hasPaidTariffRole(): bool
    {
        app(PermissionRegistrar::class)->setPermissionsTeamId(1);
        $this->loadMissing('roles');
        $roles = $this->getRoleNames();

        foreach (array_filter((array) config('cabinet-users.paid_tariff_role_codes', [])) as $code) {
            if ($roles->contains($code)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Эффективный тариф Free: роль Free и нет платной тарифной роли.
     */
    public function onFreeTariff(): bool
    {
        if ($this->hasPaidTariffRole()) {
            return false;
        }

        app(PermissionRegistrar::class)->setPermissionsTeamId(1);
        $this->loadMissing('roles');

        return $this->getRoleNames()->contains('Free');
    }

    /**
     * Email-оповещения мониторинга сайтов — только на платных тарифах.
     */
    public function canReceiveSiteMonitoringEmail(): bool
    {
        return $this->hasPaidTariffRole();
    }

    /**
     * Email-оповещения «Срок регистрации доменов» — только на платных тарифах.
     */
    public function canReceiveDomainInformationEmail(): bool
    {
        return $this->hasPaidTariffRole();
    }

    /**
     * Email-оповещения «Отслеживание ссылок» — только на платных тарифах.
     */
    public function canReceiveBacklinkEmail(): bool
    {
        return $this->hasPaidTariffRole();
    }

    /**
     * @param $project
     */
    public function brokenDomainNotification($project, string $dispatchEventId = 'site-mon-broken')
    {
        if (!$this->canReceiveSiteMonitoringEmail()) {
            return;
        }

        $this->notify(new BrokenDomainNotification($project, $dispatchEventId));
    }

    /**
     * @param $project
     */
    public function repairDomainNotification($project)
    {
        if (!$this->canReceiveSiteMonitoringEmail()) {
            return;
        }

        $this->notify(new RepairDomainNotification($project));
    }

    /**
     * @param $project
     */
    public function DomainInformationNotification($project)
    {
        $this->notify(new DomainInformationNotification($project));
    }

    /**
     * @param $project
     */
    public function sendNotificationAboutChangeDNS($project)
    {
        $this->notify(new sendNotificationAboutChangeDNS($project));
    }

    public function sendMonitoringLimitExhaustedNotification()
    {
        $this->notify(new MonitoringLimitExhaustedNotification());
    }

    /**
     * @param $project
     * @param $diffInDays
     */
    public function sendNotificationAboutExpirationRegistrationPeriod($project, $diffInDays)
    {
        $this->notify(new sendNotificationAboutExpirationRegistrationPeriod($project, $diffInDays));
    }

    /**
     * Input value roles for edit users
     *
     * @return mixed
     */
    public function getRoleAttribute()
    {
        return $this->roles->pluck('id');
    }

    public function session()
    {
        return $this->hasOne('App\Session')->orderBy('last_activity', 'desc');
    }

    /**
     * @return Classes\Tariffs\Tariff|mixed|null
     */
    public function tariff()
    {
        return (new Tariffs())->getTariffByUser($this);
    }

    public function tariffSettings()
    {
        return $this->hasMany(TariffSettingUserValue::class);
    }

    /**
     * @return HasMany
     */
    public function passwords()
    {
        return $this->hasMany(PasswordsGenerator::class)
            ->orderBy('id', 'desc')
            ->latest('created_at');
    }

    public function monitoringWidgets()
    {
        return $this->hasMany(MonitoringWidget::class);
    }

    public function statistics()
    {
        return $this->hasMany(UsersStatistic::class);
    }

    public function monitoringGroups()
    {
        return $this->belongsToMany(MonitoringGroup::class)->withTimestamps();
    }

    public function monitoringProjects()
    {
        return $this->belongsToMany(MonitoringProject::class)->withPivot('admin', 'approved', 'status');
    }

    public function monitoringProjectsDataTable()
    {
        return $this->monitoringProjects()->with('users');
    }

    public function balances()
    {
        return $this->hasMany(Balance::class);
    }

    public function pay()
    {
        return $this->hasMany(TariffPay::class);
    }

    public function visitStatistics(): HasMany
    {
        return $this->hasMany(VisitStatistic::class);
    }

    public function metaTags()
    {
        return $this->hasMany(MetaTag::class);
    }

    /**
     * @deprecated Используйте {@see deleteUnverifiedOlderThan()} из cron DeleteUnverifiedUsers.
     */
    public function deleteNoVerify(): int
    {
        return static::deleteUnverifiedOlderThan($this->delete);
    }

    /**
     * Удалить аккаунты без email_verified_at, зарегистрированные раньше чем $days дней назад.
     */
    public static function deleteUnverifiedOlderThan(int $days): int
    {
        return static::query()
            ->whereNull('email_verified_at')
            ->where('created_at', '<=', Carbon::now()->subDays($days))
            ->delete();
    }

    public function backlingProjects()
    {
        return $this->hasMany(ProjectTracking::class);
    }

    /**
     * @return bool
     */
    public function canCommentOnNews(): bool
    {
        if (self::isUserAdmin()) {
            return true;
        }

        return $this->news_comments_blocked_at === null;
    }

    public function isNewsCommentsBlocked(): bool
    {
        return $this->news_comments_blocked_at !== null;
    }

    public static function isUserAdmin(): bool
    {
        if (!Auth::check()) {
            return false;
        }

        apply_global_team_permissions();
        $user = Auth::user();
        $user->loadMissing('roles');

        if ($user->hasRole(['admin', 'Super Admin'])) {
            return true;
        }

        foreach ($user->roles as $role) {
            if (in_array((int) $role->id, [1, 3], true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return HasMany
     */
    public function project(): HasMany
    {
        return $this->hasMany(ProjectRelevanceHistory::class);
    }

    /**
     * Get the user's full name.
     *
     * @return string
     */
    public function getFullNameAttribute(): string
    {
        $arFio = array_unique([$this->name, $this->last_name]);

        return implode(" ", $arFio);
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeTelegramConnected($query)
    {
        return $query->where('telegram_bot_active', true)
            ->whereNotNull('chat_id')
            ->where('chat_id', '!=', '');
    }

    public function isTelegramConnected(): bool
    {
        return (bool) $this->telegram_bot_active && !empty($this->chat_id);
    }

    /**
     * Внешние оповещения мониторинга сайтов (email или Telegram), не только таблица в кабинете.
     */
    public function receivesSiteMonitoringExternalAlerts(bool $projectNotificationsEnabled = true): bool
    {
        if (!$projectNotificationsEnabled) {
            return false;
        }

        if (SiteMonitoringConfig::telegramEnabled() && $this->isTelegramConnected()) {
            return true;
        }

        if (SiteMonitoringConfig::emailEnabled() && $this->canReceiveSiteMonitoringEmail()) {
            return true;
        }

        return false;
    }

    public function shouldShowTelegramConnectPrompt(): bool
    {
        if ($this->isTelegramConnected()) {
            return false;
        }

        if ($this->telegram_prompt_snoozed_until && $this->telegram_prompt_snoozed_until->isFuture()) {
            return false;
        }

        return true;
    }

    /**
     * Free + сохранённое расписание съёма — модалка на страницах мониторинга позиций.
     */
    public function shouldShowMonitoringSchedulePaidPrompt(): bool
    {
        if (!$this->onFreeTariff()) {
            return false;
        }

        if ($this->monitoring_schedule_prompt_snoozed_until
            && $this->monitoring_schedule_prompt_snoozed_until->isFuture()) {
            return false;
        }

        return \App\Support\MonitoringPositionsSchedule::hasConfiguredScheduleForUser($this);
    }

    public function telegramBotSubscribeUrl(): string
    {
        $username = config('app.telegram_bot_username', 'TitloServiceBot');

        return 'https://t.me/' . $username . '?start=' . \App\Support\TelegramStartPayload::encodeEmail($this->email);
    }
}
