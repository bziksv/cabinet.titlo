<?php

namespace App\Classes\Monitoring;

use App\MonitoringKeywordPrice;
use Illuminate\Support\Collection;

class Mastered
{
    protected $positions;
    protected $price;

    /** @var \Illuminate\Support\Collection<int, MonitoringKeywordPrice>|null keyword_id => row */
    protected $priceByKeyword;

    private $top1;
    private $top3;
    private $top5;
    private $top10;
    private $top20;
    private $top50;
    private $top100;

    /** Порядок топов от узкого к широкому (для каскада цен). */
    private const TOP_FIELDS = [
        1 => 'top1',
        3 => 'top3',
        5 => 'top5',
        10 => 'top10',
        20 => 'top20',
        50 => 'top50',
        100 => 'top100',
    ];

    /**
     * @param Collection|null $priceByKeyword предзагруженные цены (monitoring_keyword_id => row)
     */
    public function __construct(Collection $positions, $priceByKeyword = null)
    {
        $this->price = new MonitoringKeywordPrice();
        $this->priceByKeyword = $priceByKeyword;
        $this->positions = $positions->values();

        foreach ($this->positions as $position) {
            $keywordId = $this->keywordId($position);
            $engineId = $this->engineId($position);
            if ($keywordId && is_object($position)) {
                $position->query_id = $keywordId;
            }
            if ($engineId && is_object($position)) {
                $position->engine_id = $engineId;
            }
        }
    }

    public function percentOfDay($budget)
    {
        if (empty($budget)) {
            return null;
        }

        return floor($this->total() / ($budget / 30) * 100);
    }

    public function percentOf($budget)
    {
        if (empty($budget)) {
            return null;
        }

        return round(($this->total() / $budget) * 100, 2);
    }

    public function total()
    {
        $top1 = $this->top1();
        $top3 = $this->top3();
        $top5 = $this->top5();
        $top10 = $this->top10();
        $top20 = $this->top20();
        $top50 = $this->top50();
        $top100 = $this->top100();

        return array_sum([
            $top1['total'],
            $top3['total'],
            $top5['total'],
            $top10['total'],
            $top20['total'],
            $top50['total'],
            $top100['total'],
        ]);
    }

    public function top1()
    {
        if ($this->top1) {
            return $this->top1;
        }

        $positions = $this->positions->where('position', 1);
        $price = $this->calcPrice($positions, 1);
        $this->top1 = ['count' => $positions->count(), 'total' => $price];

        return $this->top1;
    }

    public function top3()
    {
        if ($this->top3) {
            return $this->top3;
        }

        $this->top3 = $this->range(2, 3);

        return $this->top3;
    }

    public function top5()
    {
        if ($this->top5) {
            return $this->top5;
        }

        $this->top5 = $this->range(4, 5);

        return $this->top5;
    }

    public function top10()
    {
        if ($this->top10) {
            return $this->top10;
        }

        $this->top10 = $this->range(6, 10);

        return $this->top10;
    }

    public function top20()
    {
        if ($this->top20) {
            return $this->top20;
        }

        $this->top20 = $this->range(11, 20);

        return $this->top20;
    }

    public function top50()
    {
        if ($this->top50) {
            return $this->top50;
        }

        $this->top50 = $this->range(21, 50);

        return $this->top50;
    }

    public function top100()
    {
        if ($this->top100) {
            return $this->top100;
        }

        $this->top100 = $this->range(51, 100);

        return $this->top100;
    }

    private function range($start, $end)
    {
        $positions = $this->positions->filter(static function ($position) use ($start, $end) {
            $pos = (int) (is_object($position) ? ($position->position ?? 0) : ($position['position'] ?? 0));

            return $pos >= $start && $pos <= $end;
        });

        $price = $this->calcPrice($positions, (int) $end);

        return ['count' => $positions->count(), 'total' => $price];
    }

    /**
     * Сумма: каждый день в диапазоне × цена топа (с каскадом на более широкий топ, если свой не задан).
     * Пример: заполнена только top10 → дни в 1/3/5/10 считаются по цене top10.
     */
    private function calcPrice($positions, int $bandEnd)
    {
        $price = 0;

        foreach ($positions as $position) {
            $keywordId = $this->keywordId($position);
            $engineId = $this->engineId($position);
            $price += $this->priceForBand($keywordId, $engineId, $bandEnd);
        }

        return $price;
    }

    private function priceForBand($queryId, $engineId, int $bandEnd): float
    {
        if (!$queryId) {
            return 0;
        }

        foreach (self::TOP_FIELDS as $top => $field) {
            if ($top < $bandEnd) {
                continue;
            }
            $value = (float) $this->getPrice($queryId, $engineId, $field);
            if ($value > 0) {
                return $value;
            }
        }

        return 0;
    }

    private function getPrice($queryId, $engineId, $value)
    {
        if ($this->priceByKeyword !== null) {
            $row = $this->priceByKeyword->get($queryId);

            return $row ? (float) ($row->{$value} ?? 0) : 0;
        }

        if (!$queryId || !$engineId) {
            return 0;
        }

        $found = $this->price->newQuery()
            ->where('monitoring_keyword_id', $queryId)
            ->where('monitoring_searchengine_id', $engineId)
            ->value($value);

        return (float) ($found ?? 0);
    }

    private function keywordId($position): int
    {
        if (is_array($position)) {
            return (int) ($position['query_id'] ?? $position['monitoring_keyword_id'] ?? 0);
        }

        if ($position instanceof \Illuminate\Support\Collection) {
            return (int) ($position->get('query_id') ?? $position->get('monitoring_keyword_id') ?? 0);
        }

        return (int) ($position->query_id ?? $position->monitoring_keyword_id ?? 0);
    }

    private function engineId($position): int
    {
        if (is_array($position)) {
            return (int) ($position['engine_id'] ?? $position['monitoring_searchengine_id'] ?? 0);
        }

        if ($position instanceof \Illuminate\Support\Collection) {
            return (int) ($position->get('engine_id') ?? $position->get('monitoring_searchengine_id') ?? 0);
        }

        return (int) ($position->engine_id ?? $position->monitoring_searchengine_id ?? 0);
    }
}
