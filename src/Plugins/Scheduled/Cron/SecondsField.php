<?php

namespace Yew\Plugins\Scheduled\Cron;

use DateTimeInterface;

/**
 * Second field.  Allows: * , / -
 */
class SecondsField extends AbstractField
{
    /**
     * @inheritDoc
     */
    protected int $rangeStart = 0;

    /**
     * @inheritDoc
     */
    protected int $rangeEnd = 59;

    /**
     * @inheritDoc
     */
    public function isSatisfiedBy(DateTimeInterface $date, string $value): bool
    {
        if ($value == '?') {
            return true;
        }

        return $this->isSatisfied($date->format('s'), $value);
    }

    /**
     * {@inheritDoc}
     *
     * @param \DateTime|\DateTimeImmutable &$date
     * @param string|null $parts
     */
    public function increment(DateTimeInterface &$date, bool $invert = false, ?string $parts = null)
    {
        if (is_null($parts)) {
            $date = $date->modify(($invert ? '-' : '+') . '1 second');
            return $this;
        }

        $parts = strpos($parts, ',') !== false ? explode(',', $parts) : array($parts);
        $seconds = array();
        foreach ($parts as $part) {
            $seconds = array_merge($seconds, $this->getRangeForExpression($part, 59));
        }

        $current_second = $date->format('s');
        $position = $invert ? count($seconds) - 1 : 0;
        if (count($seconds) > 1) {
            for ($i = 0; $i < count($seconds) - 1; $i++) {
                if ((!$invert && $current_second >= $seconds[$i] && $current_second < $seconds[$i + 1]) ||
                    ($invert && $current_second > $seconds[$i] && $current_second <= $seconds[$i + 1])) {
                    $position = $invert ? $i : $i + 1;
                    break;
                }
            }
        }

        if ((!$invert && $current_second >= $seconds[$position]) || ($invert && $current_second <= $seconds[$position])) {
            $date = $date->modify(($invert ? '-' : '+') . '1 minute');
            $date = $date->setTime($date->format('H'),$date->format('i'), $invert ? 59 : 0);
        }
        else {
            $date = $date->setTime($date->format('H'),$date->format('i'), $seconds[$position]);
        }

        return $this;
    }
}
