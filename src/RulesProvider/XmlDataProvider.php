<?php

namespace Maximaster\ProductionCalendar\RulesProvider;

use Exception;
use Maximaster\ProductionCalendar\Rules;
use Maximaster\ProductionCalendar\Tools\MultiCurlWrapper;

class XmlDataProvider implements ProviderInterface
{
    protected $curl;
    protected $startYear = '2013';
    protected $urlMask = 'http://xmlcalendar.ru/data/ru/%u/calendar.xml';

    protected $rulesMap = [
        '1' => Rules::HOLIDAY,
        '2' => Rules::PRE_HOLIDAY,
        '3' => Rules::REGULAR
    ];

    public function __construct(ProviderInterface $parent)
    {
        $this->curl = new MultiCurlWrapper([
            CURLOPT_TIMEOUT => 1,
            CURLOPT_CONNECTTIMEOUT => 1,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FAILONERROR => true,
        ]);
    }

    /**
     * @return Rules
     * @throws Exception
     */
    public function get()
    {
        $data = $this->fetchData();
        $rules = new Rules;

        foreach ($data as $doc) {
            $xml = simplexml_load_string($doc);
            $year = (string)$xml->attributes()['year'];
            foreach ($xml->days->day as $dayInfo) {
                list($month, $day) = explode('.', (string)$dayInfo['d']);
                $type = $this->rulesMap[(string)$dayInfo['t']];
                $rules->addDay($year, $month, $day, $type ?: Rules::UNKNOWN);
            }
        }

        $rules->setWeekRestDays([6,7]);

        return $rules;
    }

    /**
     * @throws Exception
     */
    protected function fetchData()
    {
        $urls = array_map(function ($year) {
            return sprintf($this->urlMask, $year);
        }, range($this->startYear, date('Y')));

        $rawData = $this->curl->query($urls);

        return array_values($rawData);
    }
}