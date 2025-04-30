<?php

namespace Yew\Plugins\Scheduled\Cron;

use DateTimeInterface;
use DateTimeZone;

/**
 * Hours field.  Allows: * , / -
 */
class HoursField extends AbstractField
{
    /**
     * @var int
     */
    protected int $rangeStart = 0;

    /**
     * @var int
     */
    protected int $rangeEnd = 23;

    /**
     * @param DateTimeInterface $date
     * @param string $value
     * @return bool
     */
    public function isSatisfiedBy(DateTimeInterface $date, string $value): bool
    {
        if ($value == '?') {
            return true;
        }

        return $this->isSatisfied($date->format('H'), $value);
    }

    /**
     *
     * @param DateTimeInterface &$date
     * @param bool $invert
     * @param string|null $parts
     */
    public function increment(DateTimeInterface &$date, bool $invert = false, ?string $parts = null)
    {
        // Change timezone to UTC temporarily. This will
        // allow us to go back or forwards and hour even
        // if DST will be changed between the hours.
        if (is_null($parts) || $parts == '*') {
            $timezone = $date->getTimezone();
            $date = $date->setTimezone(new DateTimeZone('UTC'));
            $date = $date->modify(($invert ? '-' : '+') . '1 hour');
            $date = $date->setTimezone($timezone);

            $date = $date->setTime($date->format('H'), $invert ? 59 : 0);
            return $this;
        }

        $parts = strpos($parts, ',') !== false ? explode(',', $parts) : array($parts);
        $hours = array();
        foreach ($parts as $part) {
            $hours = array_merge($hours, $this->getRangeForExpression($part, 23));
        }

        $current_hour = $date->format('H');
        $position = $invert ? count($hours) - 1 : 0;
        if (count($hours) > 1) {
            for ($i = 0; $i < count($hours) - 1; $i++) {
                if ((!$invert && $current_hour >= $hours[$i] && $current_hour < $hours[$i + 1]) ||
                    ($invert && $current_hour > $hours[$i] && $current_hour <= $hours[$i + 1])) {
                    $position = $invert ? $i : $i + 1;
                    break;
                }
            }
        }

        $hour = $hours[$position];
        if ((!$invert && $date->format('H') >= $hour) || ($invert && $date->format('H') <= $hour)) {
            $date = $date->modify(($invert ? '-' : '+') . '1 day');
            $date = $date->setTime($invert ? 23 : 0, $invert ? 59 : 0);
        }
        else {
            $date = $date->setTime($hour, $invert ? 59 : 0);
        }

        return $this;
    }
}
