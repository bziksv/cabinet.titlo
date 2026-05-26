<?php

namespace App;

use App\Support\DomainInformationDns;
use App\Support\DomainInformationDisplay;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Iodev\Whois\Exceptions\ConnectionException;
use Iodev\Whois\Exceptions\ServerMismatchException;
use Iodev\Whois\Exceptions\WhoisException;
use Iodev\Whois\Factory;

class DomainInformation extends Model
{
    protected $table = 'domain_information';

    protected $guarded = [];

    public function checkLogs()
    {
        return $this->hasMany(DomainInformationCheckLog::class, 'domain_information_id');
    }

    /**
     * @param  DomainInformation  $project
     * @param  string  $source  cron|manual
     */
    public static function checkDomain($project, string $source = 'cron'): void
    {
        $oldState = $project->broken;
        $oldDNS = $project->dns;
        $whois = Factory::get()->createWhois();
        $project->last_check = Carbon::now();

        try {
            $info = $whois->loadDomainInfo($project->domain);
            if (isset($info)) {
                $project->broken = false;
                $newDns = DomainInformationDns::formatFromNameServers($info->nameServers ?? []);
                $registrationDate = __('Registration date') . ' ' . date('Y-m-d', $info->creationDate);
                $freeDate = date('Y-m-d', $info->expirationDate);
                $project->dns = $newDns;
                $project->domain_information = self::formatRegistrationSummary($registrationDate, $freeDate);
                DomainInformation::sendNotifications($project, $oldState, $oldDNS, $newDns, $freeDate);
            } else {
                $project->broken = true;
                $project->domain_information = __('This domain has been removed from delegation(is free) and it can be registered.');
                DomainInformation::sendNotifications($project, $oldState);
            }
        } catch (\Exception $exception) {
            $project->broken = true;
            $project->domain_information = __('This domain has been removed from delegation(is free) and it can be registered.');
            DomainInformation::sendNotifications($project, $oldState);
        }

        $project->save();

        self::recordCheckLog($project, $source, $oldDNS);
    }

    public static function recordCheckLog(DomainInformation $project, string $source, ?string $oldDns = null): void
    {
        $dnsChanged = DomainInformationDns::hasChanged($oldDns, $project->dns);

        DomainInformationCheckLog::create([
            'domain_information_id' => $project->id,
            'user_id' => $project->user_id,
            'broken' => (bool) $project->broken,
            'info_snapshot' => DomainInformationDisplay::dnsBlock($project) . "\n\n" . DomainInformationDisplay::registrationBlock($project),
            'dns_changed' => $dnsChanged,
            'source' => $source === 'manual' ? 'manual' : 'cron',
            'created_at' => Carbon::now(),
        ]);
    }

    public static function formatRegistrationSummary(string $registrationDate, string $freeDate): string
    {
        $date = new Carbon($freeDate);

        return $registrationDate . "\n"
            . __('Registration expires')
            . $freeDate
            . ' '
            . __('through')
            . ' '
            . $date->diffInDays(Carbon::now())
            . ' '
            . __('days');
    }

    /**
     * @param  DomainInformation  $project
     * @param  bool  $oldState
     * @param  string|null  $oldDNS
     * @param  string|null  $newDNS
     * @param  string|null  $freeDate
     */
    public static function sendNotifications($project, $oldState, $oldDNS = null, $newDNS = null, $freeDate = null)
    {
        $user = User::find($project->user_id);

        if (DomainInformationDns::hasChanged($oldDNS, $newDNS ?? $project->dns)) {
            if ($user->telegram_bot_active and $project->check_dns) {
                TelegramBot::sendNotificationAboutChangeDNS($project, $user->chat_id, $oldDNS);
            }

            if ($project->check_dns_email && $user->canReceiveDomainInformationEmail()) {
                $user->sendNotificationAboutChangeDNS($project);
            }
        }

        if (isset($freeDate)) {
            $freeDate = new Carbon($freeDate);
            $diffInDays = $freeDate->diffInDays(Carbon::now());

            if ($diffInDays < 20) {
                if ($user->telegram_bot_active and $project->check_registration_date) {
                    TelegramBot::sendNotificationAboutExpirationRegistrationPeriod($project, $user->chat_id, $diffInDays);
                }

                if ($project->check_registration_date_email && $user->canReceiveDomainInformationEmail()) {
                    $user->sendNotificationAboutExpirationRegistrationPeriod($project, $diffInDays);
                }
            }
        }
    }

    /**
     * @param $domain
     * @return bool
     */
    public static function isValidDomain($domain): bool
    {
        return (
            preg_match("/^([a-z\d](-*[a-z\d])*)(\.([a-z\d](-*[a-z\d])*))*$/i", $domain) &&
            preg_match("/^.{1,253}$/", $domain) &&
            preg_match("/^[^\.]{1,63}(\.[^\.]{1,63})*$/", $domain)
        );
    }

    /**
     * @param $link
     * @return string
     */
    public static function getDomain($link): string
    {
        $information = parse_url($link);

        return $information['host'] ?? $link;
    }

    /**
     * Разовая WHOIS-проверка для демо на маркетинге (без сохранения в БД).
     *
     * @return array<string, mixed>
     */
    public static function probe(string $rawDomain): array
    {
        $domain = self::getDomain(trim($rawDomain));
        if (!self::isValidDomain($domain)) {
            return [
                'ok' => false,
                'domain' => $domain,
                'broken' => true,
                'status' => (string) __('Domain information status error'),
                'status_key' => 'invalid',
                'message' => (string) __('There is no such domain'),
                'dns' => '',
                'dns_servers' => [],
                'registered_at' => null,
                'expires_at' => null,
                'days_until_expiry' => null,
                'registration_summary' => '',
            ];
        }

        $whois = Factory::get()->createWhois();

        try {
            $info = $whois->loadDomainInfo($domain);
            if (!isset($info)) {
                $message = (string) __('This domain has been removed from delegation(is free) and it can be registered.');

                return [
                    'ok' => true,
                    'domain' => $domain,
                    'broken' => true,
                    'status' => (string) __('Domain information status error'),
                    'status_key' => 'free',
                    'message' => $message,
                    'dns' => '',
                    'dns_servers' => [],
                    'registered_at' => null,
                    'expires_at' => null,
                    'days_until_expiry' => null,
                    'registration_summary' => $message,
                ];
            }

            $dns = DomainInformationDns::formatFromNameServers($info->nameServers ?? []);
            $dnsServers = DomainInformationDns::normalizeList($info->nameServers ?? []);
            $registeredAt = isset($info->creationDate) ? date('Y-m-d', $info->creationDate) : null;
            $expiresAt = isset($info->expirationDate) ? date('Y-m-d', $info->expirationDate) : null;
            $daysLeft = $expiresAt !== null ? (new Carbon($expiresAt))->diffInDays(Carbon::now()) : null;
            $registrationSummary = $registeredAt && $expiresAt
                ? self::formatRegistrationSummary(
                    __('Registration date') . ' ' . $registeredAt,
                    $expiresAt
                )
                : '';

            return [
                'ok' => true,
                'domain' => $domain,
                'broken' => false,
                'status' => (string) __('Domain information status ok'),
                'status_key' => 'ok',
                'message' => '',
                'dns' => $dns,
                'dns_servers' => $dnsServers,
                'registered_at' => $registeredAt,
                'expires_at' => $expiresAt,
                'days_until_expiry' => $daysLeft,
                'registration_summary' => $registrationSummary,
            ];
        } catch (\Exception $exception) {
            $message = (string) __('This domain has been removed from delegation(is free) and it can be registered.');

            return [
                'ok' => true,
                'domain' => $domain,
                'broken' => true,
                'status' => (string) __('Domain information status error'),
                'status_key' => 'error',
                'message' => $message,
                'dns' => '',
                'dns_servers' => [],
                'registered_at' => null,
                'expires_at' => null,
                'days_until_expiry' => null,
                'registration_summary' => $message,
            ];
        }
    }

}
