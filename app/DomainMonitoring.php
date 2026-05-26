<?php

namespace App;

use App\SiteMonitoringConfig;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

class DomainMonitoring extends Model
{
    /** Статус в таблице после сброса uptime (до следующей проверки). */
    public const STATUS_AFTER_RESET = 'Site monitoring status after reset';

    protected $guarded = [];

    protected $table = 'domain_monitoring';

    /**
     * @return HasOne
     */
    public function telegramBot(): HasOne
    {
        return $this->hasOne(TelegramBot::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function checkLogs(): HasMany
    {
        return $this->hasMany(DomainMonitoringCheckLog::class, 'domain_monitoring_id');
    }

    public function isPendingResetStatus(): bool
    {
        return (string) $this->status === self::STATUS_AFTER_RESET;
    }

    public function resetStatistics(): void
    {
        $this->applyStatisticsResetState();
        $this->save();
    }

    /**
     * @return array<string, mixed>
     */
    protected function applyStatisticsResetState(): void
    {
        $this->up_time = 0;
        $this->uptime_percent = 100;
        $this->time_last_breakdown = null;
        $this->total_time_last_breakdown = null;
        $this->broken = false;
        $this->status = self::STATUS_AFTER_RESET;
        $this->code = null;
    }

    /**
     * Bulk reset uptime for all projects of a user; clears last HTTP snapshot in «Состояние».
     */
    public static function resetStatisticsForUser(int $userId): int
    {
        return static::query()
            ->where('user_id', $userId)
            ->update([
                'up_time' => 0,
                'uptime_percent' => 100,
                'time_last_breakdown' => null,
                'total_time_last_breakdown' => null,
                'broken' => false,
                'status' => self::STATUS_AFTER_RESET,
                'code' => null,
            ]);
    }

    public static function calculateUpTime($project)
    {
        $created = new Carbon($project->created_at);
        $lastCheck = new Carbon($project->last_check);
        $totalTime = $created->diffInSeconds(Carbon::now());
        if ($project->last_check === null) {
            if ($project->broken) {
                return $project->uptime_percent = 0;
            } else {
                $project->up_time = $totalTime;
                return $project->uptime_percent = 100;
            }
        }
        if ($project->broken) {
            return $project->uptime_percent = $project->up_time / ($totalTime / 100);
        }

        $project->up_time += $lastCheck->diffInSeconds(Carbon::now());
        $project->uptime_percent = $project->up_time / ($totalTime / 100);
        $project->save();
    }

    public static function calculateTotalTimeLastBreakdown($project, $oldState)
    {
        if ($oldState && !$project->broken) {
            $timeLastBreakdown = new Carbon($project->time_last_breakdown);
            $project->total_time_last_breakdown = $timeLastBreakdown->diffInMinutes(Carbon::now());
        }

        if (!$oldState && $project->broken) {
            $project->time_last_breakdown = Carbon::now();
        }
    }

    public static function sendNotifications($project, $oldState)
    {
        if (!$project->send_notification) {
            return;
        }

        $user = User::where('id', '=', $project->user_id)->first();
        if (!$user) {
            return;
        }

        $emailOn = SiteMonitoringConfig::emailEnabled() && $user->canReceiveSiteMonitoringEmail();
        $telegramOn = SiteMonitoringConfig::telegramEnabled()
            && $user->telegram_bot_active
            && $user->chat_id;
        $repeatMinutes = SiteMonitoringConfig::repeatBrokenMinutes();

        if ($oldState && !$project->broken) {
            if ($emailOn) {
                $user->repairDomainNotification($project);
            }
            if ($telegramOn) {
                TelegramBot::repairedDomainNotification($project, $user->chat_id);
            }
            $project->time_last_notification = Carbon::now();
        }

        if (!$oldState && $project->broken) {
            if ($emailOn) {
                $user->brokenDomainNotification($project);
            }
            if ($telegramOn) {
                TelegramBot::brokenDomainNotification($project, $user->chat_id);
            }
            $project->time_last_notification = Carbon::now();
        }

        $lastNotification = $project->time_last_notification
            ? new Carbon($project->time_last_notification)
            : null;

        if ($oldState && $project->broken && (
            $lastNotification === null
            || $lastNotification->diffInMinutes(Carbon::now()) >= $repeatMinutes
        )) {
            if ($emailOn) {
                $user->brokenDomainNotification($project);
            }
            if ($telegramOn) {
                TelegramBot::brokenDomainNotification($project, $user->chat_id);
            }
            $project->time_last_notification = Carbon::now();
        }

        $project->save();
    }

    public static function httpCheck($project, string $source = 'cron'): void
    {
        $oldState = $project->broken;
        self::runCheck($project);

        DomainMonitoring::calculateTotalTimeLastBreakdown($project, $oldState);
        DomainMonitoring::calculateUpTime($project);
        $project->last_check = Carbon::now();
        $project->save();

        self::recordCheckLog($project, $source);

        try {
            DomainMonitoring::sendNotifications($project, $oldState);
        } catch (\Throwable $e) {

        }

    }

    public static function recordCheckLog(DomainMonitoring $project, string $source): void
    {
        DomainMonitoringCheckLog::create([
            'domain_monitoring_id' => $project->id,
            'user_id' => $project->user_id,
            'broken' => (bool) $project->broken,
            'status' => $project->status,
            'http_code' => $project->code,
            'uptime_percent' => $project->uptime_percent,
            'source' => $source === 'manual' ? 'manual' : 'cron',
            'created_at' => Carbon::now(),
        ]);
    }

    /**
     * Одна проверка URL без сохранения в БД (демо на маркетинге).
     *
     * @return array{broken: bool, status: string, status_key: string, code: int, response_time_ms: int, phrase_used: bool}
     */
    public static function probe(string $link, ?string $phrase = null, int $waitingTime = 15): array
    {
        $project = new self([
            'link' => $link,
            'phrase' => $phrase !== null && trim($phrase) !== '' ? trim($phrase) : null,
            'waiting_time' => max(10, min(20, $waitingTime)),
        ]);

        $started = microtime(true);
        self::runCheck($project);

        return [
            'broken' => (bool) $project->broken,
            'status' => __($project->status),
            'status_key' => (string) $project->status,
            'code' => (int) ($project->code ?? 0),
            'response_time_ms' => (int) round((microtime(true) - $started) * 1000),
            'phrase_used' => $project->phrase !== null && $project->phrase !== '',
        ];
    }

    /**
     * HTTP/фраза — без записи в БД.
     */
    public static function runCheck($project): void
    {
        $curl = DomainMonitoring::curlInit($project);
        if (isset($curl) && $curl[1]['http_code'] === 200) {
            if (isset($project->phrase) && $project->phrase !== '') {
                DomainMonitoring::searchPhrase($curl, $project);
            } else {
                $project->status = 'Everything all right';
                $project->broken = false;
            }
            $project->code = 200;
        } else {
            $project->status = 'Unexpected response code';
            $project->code = isset($curl[1]['http_code']) ? $curl[1]['http_code'] : 0;
            $project->broken = true;
        }
    }

    public static function curlInit($project): ?array
    {
        $refers = ['google.com', 'yandex.ru'];

        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $project->link);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($curl, CURLOPT_COOKIE, 'realauth=SvBD85dINu3; expires=Sat, 25 Feb 2030 02:16:43 GMT; path=/; SameSite=Lax');
        curl_setopt($curl, CURLOPT_HEADER, true);
        curl_setopt($curl, CURLOPT_ENCODING, 'UTF-8');
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, $project->waiting_time);
        curl_setopt($curl, CURLOPT_TIMEOUT, $project->waiting_time);
        curl_setopt($curl, CURLOPT_FAILONERROR, true);
        curl_setopt($curl, CURLOPT_REFERER, $refers[array_rand($refers)]);

        return DomainMonitoring::tryConnect($curl);
    }

    /**
     * @param $curl
     * @return array|null
     */
    public static function tryConnect($curl): ?array
    {
        $html = null;
        $headers = null;
        $userAgents = [
            //Mozilla Firefox
            'Mozilla/5.0 (Windows NT 6.3; Win64; x64; rv:87.0) Gecko/20100101 Firefox/87.0',
            'Mozilla/5.0 (Windows NT 10.0; rv:87.0) Gecko/20100101 Firefox/87.0',
//            opera
            'Mozilla/5.0 (Windows NT 6.1; Win64; x64) AppleWebKit/537.43 (KHTML, like Gecko) Chrome/93.0.4577.82 Safari/537.36 OPR/79.0.4143.72',
            'Mozilla/5.0 (Windows NT 6.3) AppleWebKit/537.43 (KHTML, like Gecko) Chrome/93.0.4577.82 Safari/537.36 OPR/79.0.4143.72',
            // chrome
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/93.0.4577.63 Safari/537.36',
            'Mozilla/5.0 (Windows NT 6.1) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/92.0.4515.159 Safari/537.36'
        ];

        for ($i = 0; $i < count($userAgents); $i++) {
            curl_setopt($curl, CURLOPT_USERAGENT, $userAgents[$i]);
            $html = curl_exec($curl);
            $headers = curl_getinfo($curl);
            if (curl_error($curl) == "transfer closed with outstanding read data remaining") {
                curl_setopt($curl, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_0);
            }

            if ($headers['http_code'] == 200 && $html) {
                $html = preg_replace('//i', '', $html);
                break;
            }
        }
        curl_close($curl);
        return [$html, $headers];
    }

    /**
     * @param $curl
     * @param $project
     */
    public static function searchPhrase($curl, $project)
    {
        $contentType = $curl[1]['content_type'];
        if (preg_match('(.*?charset=(.*))', $contentType, $contentType, PREG_OFFSET_CAPTURE)) {
            $contentType = str_replace(array("\r", "\n"), '', $contentType[1][0]);
            $phrase = mb_convert_encoding($project->phrase, $contentType);
        } else {
            $phrase = $project->phrase;
        }

        try {
            if (strripos($curl[0], $phrase) !== false) {
                $project->status = 'Everything all right';
                $project->broken = false;
            } else {
                $project->status = 'Keyword not found';
                $project->broken = true;
            }
        } catch (\Throwable $e) {
            Log::debug('site monitoring error', [$project]);
            $project->status = 'Keyword not found';
            $project->broken = true;
        }

    }
}
