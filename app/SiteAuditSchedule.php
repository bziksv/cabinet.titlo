<?php

namespace App;

use App\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

class SiteAuditSchedule extends Model
{
    protected $table = 'site_audit_schedules';

    public const FREQ_WEEKLY = 'weekly';
    public const FREQ_BIWEEKLY = 'biweekly';
    public const FREQ_TRIWEEKLY = 'triweekly';
    public const FREQ_MONTHLY = 'monthly';

    /** @var string[] */
    public const FREQUENCIES = [
        self::FREQ_WEEKLY,
        self::FREQ_BIWEEKLY,
        self::FREQ_TRIWEEKLY,
        self::FREQ_MONTHLY,
    ];

    protected $fillable = [
        'user_id',
        'project_id',
        'domain',
        'enabled',
        'frequency',
        'settings_json',
        'last_run_at',
        'next_run_at',
    ];

    protected $casts = [
        'enabled' => 'boolean',
        'settings_json' => 'array',
        'last_run_at' => 'datetime',
        'next_run_at' => 'datetime',
    ];

    public function project()
    {
        return $this->belongsTo(SiteAuditProject::class, 'project_id');
    }

    /**
     * Расписание только на платных тарифах (Optimal / Ultimate / Maximum).
     */
    public static function allowedForUser(?User $user): bool
    {
        return $user && $user->hasPaidTariffRole();
    }

    /**
     * @return array<string,string> code => label
     */
    public static function frequencyLabels(): array
    {
        return [
            self::FREQ_WEEKLY => 'раз в неделю',
            self::FREQ_BIWEEKLY => 'раз в 2 недели',
            self::FREQ_TRIWEEKLY => 'раз в 3 недели',
            self::FREQ_MONTHLY => 'раз в месяц',
        ];
    }

    public static function normalizeFrequency(?string $frequency): string
    {
        $frequency = (string) $frequency;
        // legacy
        if ($frequency === 'daily') {
            return self::FREQ_WEEKLY;
        }
        if (in_array($frequency, self::FREQUENCIES, true)) {
            return $frequency;
        }

        return self::FREQ_WEEKLY;
    }

    public function computeNextRun(?Carbon $from = null): Carbon
    {
        $from = $from ?: Carbon::now();
        $freq = self::normalizeFrequency($this->frequency);

        switch ($freq) {
            case self::FREQ_BIWEEKLY:
                return $from->copy()->addWeeks(2)->startOfHour();
            case self::FREQ_TRIWEEKLY:
                return $from->copy()->addWeeks(3)->startOfHour();
            case self::FREQ_MONTHLY:
                return $from->copy()->addMonth()->startOfHour();
            case self::FREQ_WEEKLY:
            default:
                return $from->copy()->addWeek()->startOfHour();
        }
    }
}
