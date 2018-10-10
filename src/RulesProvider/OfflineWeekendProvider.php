<?php

namespace Maximaster\ProductionCalendar\RulesProvider;

use DateInterval;
use DatePeriod;
use DateTime;
use Maximaster\ProductionCalendar\Rules;

class OfflineWeekendProvider implements ProviderInterface
{

    /**
     * ProviderInterface constructor.
     * @param ProviderInterface|null $parentProvider
     */
    public function __construct(ProviderInterface $parentProvider)
    {

    }

    /**
     * @return Rules
     * @throws \Exception
     */
    public function get()
    {
        $begin = new DateTime('2000-01-01');
        $end = new DateTime(strtotime('+5 years'));

        $interval = DateInterval::createFromDateString('1 day');
        $period = new DatePeriod($begin, $interval, $end);

        $rules = new Rules;
        $endOfWeek = [6,7];

        /**
         * @var \DateTimeInterface $day
         */
        foreach ($period as $day) {
            if (!in_array($day->format('N'), $endOfWeek)) {
                continue;
            }

            $rules->addDay(
                $day->format('Y'),
                $day->format('m'),
                $day->format('d'),
                Rules::REGULAR_REST
            );
        }

        $rules->setWeekRestDays($endOfWeek);

        return $rules;
    }
}