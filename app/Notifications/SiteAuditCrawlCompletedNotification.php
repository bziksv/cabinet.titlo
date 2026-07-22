<?php

namespace App\Notifications;

use App\Contracts\EmailPreferenceAware;
use App\Notifications\Concerns\AppendsMailUnsubscribe;
use App\Notifications\Concerns\LocalizesMailContent;
use App\SiteAuditCrawl;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SiteAuditCrawlCompletedNotification extends Notification implements EmailPreferenceAware
{
    use AppendsMailUnsubscribe;
    use LocalizesMailContent;
    use Queueable;

    /** @var SiteAuditCrawl */
    public $crawl;

    public function __construct(SiteAuditCrawl $crawl)
    {
        $this->crawl = $crawl;
    }

    public function via($notifiable)
    {
        return ['mail'];
    }

    public function toMail($notifiable)
    {
        $this->applyMailLocale($notifiable);
        $this->crawl->loadMissing('project');

        $domain = optional($this->crawl->project)->domain ?? '—';
        $buckets = $this->crawl->buckets_json ?: [];
        $summary = sprintf(
            'Грубые: %d · Прочие: %d · Предупреждения: %d · Инфо: %d',
            (int) ($buckets['critical'] ?? 0),
            (int) ($buckets['other'] ?? 0),
            (int) ($buckets['warning'] ?? 0),
            (int) ($buckets['info'] ?? 0)
        );

        return $this->appendMailUnsubscribe(
            (new MailMessage)
                ->subject('Аудит сайта готов: ' . $domain)
                ->greeting('Здравствуйте!')
                ->line('Завершён технический аудит сайта «' . $domain . '» (краул #' . $this->crawl->id . ').')
                ->line('Страниц: ' . (int) $this->crawl->pages_fetched . ' / ' . (int) $this->crawl->pages_total . '.')
                ->line($summary)
                ->action('Открыть сводку', route('pages.site-audit.crawl.show', $this->crawl->id))
                ->line('Спасибо, что пользуетесь Titlo.'),
            $this->emailPreferenceKey()
        );
    }

    public function emailPreferenceKey(): ?string
    {
        return 'site-audit-crawl-done';
    }

    public function toArray($notifiable)
    {
        return [
            'crawl_id' => $this->crawl->id,
        ];
    }
}
