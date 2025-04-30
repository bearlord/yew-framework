<?php

namespace Yew\Plugins\Scheduled\Cron;

use DateTimeInterface;

/**
 * Month field.  Allows: * , / -
 */
class MonthField extends AbstractField
{
    /**
     * @var int
     */
    protected int $rangeStart = 1;

    /**
     * @var int
     */
    protected int $rangeEnd = 12;

    /**
     * @var array|string[]
     */
    protected array $literals = [
        1 => 'JAN',
        2 => 'FEB',
        3 => 'MAR',
        4 => 'APR',
        5 => 'MAY',
        6 => 'JUN',
        7 => 'JUL',
        8 => 'AUG',
        9 => 'SEP',
        10 => 'OCT',
        11 => 'NOV',
        12 => 'DEC'
    ];

    /**
     * @inheritDoc
     */
    public function isSatisfiedBy(DateTimeInterface $date, string $value): bool
    {
        if ($value == '?') {
            return true;
        }

        $value = $this->convertLiterals($value);

        return $this->isSatisfied($date->format('m'), $value);
    }

    /**
     * @param DateTimeInterface &$date
     * @param bool $invert
     * @param string|null $parts
     * @inheritDoc
     *
     */
    public function increment(DateTimeInterface &$date, bool $invert = false, ?string $parts = null)
    {
        if ($invert) {
            $date = $date->modify('last day of previous month')->setTime(23, 59);
        } else {
            $date = $date->modify('first day of next month')->setTime(0, 0);
        }

        return $this;
    }


}
