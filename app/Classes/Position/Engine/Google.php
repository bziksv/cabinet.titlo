<?php


namespace App\Classes\Position\Engine;


use App\Classes\Monitoring\MonitoringGoogleDepth;
use App\Classes\Position\PositionCollectRateLimiter;
use App\Classes\Position\Positions;
use App\Exceptions\ErrorXmlPositionResponseException;

class Google extends Positions
{
    /** @var int */
    private $googleDepth = MonitoringGoogleDepth::MIN;

    public function __construct($domain, $query, $lr, $save = true)
    {
        $this->engine = 'https://xmlstock.com/google/xml/';

        $this->domain = $domain;
        $this->query = $query;
        $this->lr = $lr;
        $this->save = $save;

        parent::__construct();
    }

    public function setGoogleDepth(int $depth): self
    {
        $this->googleDepth = MonitoringGoogleDepth::normalize($depth);

        return $this;
    }

    protected function setParams()
    {
        $this->xml->setPath($this->engine);
        $this->xml->setGroupBy('deep');
        $this->xml->setLr($this->lr);
        $this->xml->setQuery($this->query);
    }

    /**
     * @return array|int|string|null
     * @throws ErrorXmlPositionResponseException
     */
    protected function getSitePositionWithXml()
    {
        $site = $this->domain;
        $pages = MonitoringGoogleDepth::pageCount($this->googleDepth);
        $found = null;

        for ($page = 0; $page < $pages; $page++) {
            $this->xml->setPage((string) $page);
            PositionCollectRateLimiter::acquire();
            $results = $this->xml->getByArray();

            if (isset($results['response']['error'])) {
                throw new ErrorXmlPositionResponseException($results['response']['error']);
            }

            $positions = $results['response']['results']['grouping']['group'] ?? [];
            if (!is_array($positions) || $positions === []) {
                break;
            }

            if (isset($positions['doc'])) {
                $positions = [$positions];
            }

            if ($this->save) {
                $this->storePositions($positions, $page * MonitoringGoogleDepth::STEP);
            }

            $position = array_filter($positions, function ($var) use ($site) {
                $url = $var['doc']['url'] ?? null;
                if (!$url) {
                    return false;
                }
                $domain = parse_url($url);

                return $domain && !empty($domain['host'])
                    && $this->domainFilter($domain['host']) === $site;
            });

            if (count($position) > 0 && $found === null) {
                $posKey = key($position);
                $doc = $position[$posKey]['doc'];
                $doc['position'] = ($page * MonitoringGoogleDepth::STEP) + $posKey + 1;
                $found = $doc;
            }

            if (count($positions) < MonitoringGoogleDepth::STEP) {
                break;
            }
        }

        return $found;
    }
}
