<?php

namespace Maximaster\ProductionCalendar;

use DateInterval;
use DateTime;
use Exception;
use Maximaster\ProductionCalendar\RulesProvider\OfflineWeekendProvider;
use Maximaster\ProductionCalendar\RulesProvider\ProviderInterface;

class Calendar
{
    /**
     * @var Rules
     */
    protected $rules;

    /**
     * @var string
     */
    protected $innerFormat = 'd.m.Y';

    /**
     * @var bool
     */
    protected $isOfflineProvider = false;

    public function __construct(Rules $rules)
    {
        $this->rules = $rules;
    }

    /**
     * Возвращает, является ли день "свободным", т.е. либо праздником, либо рядовым выходным
     * @param string|DateTime $day
     * @return bool
     */
    public function isFreeDay($day)
    {
        return $this->isDay($day, [Rules::HOLIDAY, Rules::REGULAR_REST], true);
    }

    /**
     * Проверяет, является ли день рядовым выходным
     * @param string|DateTime $day
     * @return bool
     */
    public function isRegularRestDay($day)
    {
        return in_array(
            (int)($day instanceof DateTime ? $day->format('N') : date('N', strtotime($day))),
            $this->rules->getWeekRestDays()
        );
    }

    /**
     * Проверяет день на соответствие определённому типу по заданным правилам
     * @param string|DateTime $day
     * @param array|int $types Тип или типы
     * @param bool $validateType Осуществлять ли проверку корректности данных в $types
     * @return bool
     * @throws Exception
     */
    public function isDay($day, $types, $validateType = true)
    {
        if (!is_array($types)) {
            $types = [$types];
        }

        if ($validateType &&
            count($types) !== count(array_filter(array_map(
                ['Maximaster\ProductionCalendar\Rules', 'isCorrectType'], $types)
            ))
        ) {
            throw new Exception("Incorrect day type(s): ".var_export($types, true));
        }

        return in_array($this->getDayType($day), $types);
    }

    /**
     * Возвращает тип дня согласно заданному набору правил
     * @param string|DateTime $day
     * @return int
     */
    public function getDayType($day)
    {
        list($dayNum, $month, $year) = self::getNormalizedDay($day);
        $dayType = $this->rules->getDay($dayNum, $month, $year);
        if ($dayType === Rules::UNKNOWN) {
            $dayType = $this->isRegularRestDay($day) ? Rules::REGULAR_REST : Rules::REGULAR;
        }

        return $dayType;
    }

    /**
     * @param string|DateTime $day
     * @return array
     */
    public function getNormalizedDay($day)
    {
        return explode(
            '.',
            date($this->innerFormat, $day instanceof DateTime ? $day->getTimestamp() : strtotime($day))
        );
    }

    /**
     * Возвращает дни определённого типа(ов) между двумя указанными датами (включительно)
     * @param int|int[] $types
     * @param DateTime $from
     * @param DateTime $to
     * @return DateTime[]
     */
    public function getDays($types, DateTime $from, DateTime $to)
    {
        // Убираем время, может помешать в сравнениях
        $range = ['from' => clone $from, 'to' => clone $to];
        foreach ($range as $date) {
            $date->setTime(0, 0);
        }

        // Убираем ссылку на входные данные чтобы их не повредить
        unset($from, $to);

        // Т.к. формулировка метода "между двумя датами", лояльно отнесёмся к перепутанным местами датам
        if ($range['from'] > $range['to']) {
            list($range['to'], $range['from']) = array_values($range);
        }

        $days = [];
        do {
            if ($this->isDay($range['from'], $types)) {
                $days[] = clone $range['from'];
            }
        } while ($range['from']->add(new DateInterval('P1D')) <= $range['to']);

        return $days;
    }

    /**
     * Возвращает свободные дни указанного месяца
     * Месяц можно указать передав числами год и месяц, либо передав DateTime одного из дней нужного месяца
     * @param int|int[] $types
     * @param int|DateTime $year
     * @param int|null $month
     * @return DateTime[]
     */
    public function getMonthDays($types, $year, $month = null)
    {
        $monthDay = $year instanceof DateTime ? clone $year : (new DateTime)->setDate($year, $month, 1);
        $monthDay->setTime(0, 0);
        return $this->getDays(
            $types,
            clone $monthDay->modify('first day of this month'),
            $monthDay->modify('last day of this month')
        );
    }

    /**
     * См. getMonthDays, только возвращается не массив дней, а их количество
     * @param int|int[] $types
     * @param int|DateTime $year
     * @param int|null $month
     * @return int
     */
    public function getMonthDaysCount($types, $year, $month = null)
    {
        return count($this->getMonthDays($types, $year, $month));
    }

    /**
     * См. getDays, только возвращается не массив дней, а их количество
     * @param int|int[] $types
     * @param DateTime $from
     * @param DateTime $to
     * @return int
     */
    public function getDaysCount($types, DateTime $from, DateTime $to)
    {
        return count($this->getDays($types, $from, $to));
    }

    /**
     * Возвращает количество рабочих дней между указанными датами (включительно)
     * @param DateTime $from
     * @param DateTime $to
     * @return int
     */
    public function getWorkDaysCount(DateTime $from, DateTime $to)
    {
        return $this->getDaysCount(Rules::$WORK, $from, $to);
    }

    /**
     * Возвращает количество рабочих дней в указанном месяце
     * @param int|int[] $types
     * @param int|DateTime $year
     * @param int|null $month
     * @return int
     */
    public function getMonthWorkDaysCount($year, $month = null)
    {
        return $this->getMonthDaysCount(Rules::$WORK, $year, $month);
    }

    /**
     * @return bool
     */
    public function isOfflineProvider()
    {
        return $this->isOfflineProvider;
    }

    /**
     * @param ProviderInterface $provider
     * @param ProviderInterface[] $fallback
     * @return Calendar
     * @throws Exception
     */
    public static function fromProvider(ProviderInterface $provider, array $fallback = [])
    {
        $err = PHP_EOL;
        array_unshift($fallback, $provider);

        foreach ($fallback as $fb) {
            try {
                $ca = new self($fb->get());
                $ca->isOfflineProvider = $fb instanceof OfflineWeekendProvider;
                return $ca;
            } catch (Exception $e) {
                $err .= $e->getMessage() . PHP_EOL;
            }
        }

        throw new Exception('Can not load provider ' . $err);
    }
}
