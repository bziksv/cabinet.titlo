<?php

namespace App\Classes\Services;

use App\Classes\Xml\RiverFacade;

/**
 * Частотность Wordstat через XmlRiver (новый JSON API с pagetype=history).
 */
class XmlRiver
{
    private $query;
    private $regions;

    public function __construct($query, $regions)
    {
        $this->query = $this->filterQuery($query);
        $this->regions = $regions;
    }

    public function get(): int
    {
        $river = new RiverFacade($this->regions);
        $river->setQuery($this->query);
        $result = $river->riverRequest(false);

        return (int) ($result['number'] ?? 0);
    }

    protected function filterQuery(string $str): string
    {
        return str_replace(['+', ','], '', $str);
    }
}
