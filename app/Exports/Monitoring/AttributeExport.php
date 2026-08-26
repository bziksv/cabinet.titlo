<?php


namespace App\Exports\Monitoring;

use Illuminate\Support\Collection;
use Illuminate\Http\Request;

class AttributeExport
{
    private $collection;
    private $request;
    private $budget = 0;

    public function __construct(Collection &$collection, Request $request)
    {
        $this->collection = $collection;
        $this->request = $request;
    }

    public function execute()
    {
        if ($this->request['mode'] == 'finance') {
            $this->setTotalSum($this->getBudget());
        }

        if ($this->request['dynamicsDays']) {
            $this->removeDynamicDays();
        }

        $this->url();
    }

    protected function removeDynamicDays()
    {
        $this->collection['data']->transform(function ($item) {
            $row = $item instanceof Collection ? $item->all() : (array) $item;

            foreach ($row as $col => $val) {
                // /table отдаёт positionCellPayload — динамика в ключе d, не в <sup>.
                if (is_array($val) && array_key_exists('p', $val)) {
                    unset($val['d']);
                    $row[$col] = $val;
                    continue;
                }

                if (!is_string($val)) {
                    continue;
                }

                $row[$col] = preg_replace('/<sup[^>]*>.*?<\/sup>/su', '', $val) ?? $val;
            }

            return collect($row);
        });
    }

    protected function setTotalSum($budget)
    {
        $total = $this->collection['data']->pluck('mastered')->sum();
        $count = $this->collection['columns']->count();

        // Итоги — в конце таблицы (pad слева), не в колонке «Запрос».
        $this->collection['data']->push(collect(['Выведено фраз на сумму:', $total])->pad(-$count, ''));
        $this->collection['data']->push(collect(['Максимальный бюджет:', $budget])->pad(-$count, ''));
    }

    protected function url()
    {
        $this->collection['data']->transform(function($item){
            if ($item->has('url')) {
                $url = $item['url'];

                $doc = new \DOMDocument();
                $doc->loadHTML($url);

                $a = $doc->getElementsByTagName('a');
                $links = $a[0]->getAttribute('data-content');

                if ($links) {
                    $doc->loadHTML($links);
                    $a = $doc->getElementsByTagName('a');

                    if ($a->length) {
                        $item['url'] = strip_tags($a[$a->length - 1]->textContent);
                    }
                }
            }
            return $item;
        });
    }

    public function getBudget()
    {
        return $this->budget;
    }

    public function setBudget($budget): void
    {
        $this->budget = $budget;
    }


}
