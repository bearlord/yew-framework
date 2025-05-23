<?php

namespace Yew\Plugins\Scheduled\Cron;

use DateTime;
use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use Exception;
use InvalidArgumentException;
use RuntimeException;

/**
 * CRON expression parser that can determine whether or not a CRON expression is
 * due to run, the next run date and previous run date of a CRON expression.
 * The determinations made by this class are accurate if checked run once per
 * minute (seconds are dropped from date time comparisons).
 *
 * Schedule parts must map to:
 * minute [0-59], hour [0-23], day of month, month [1-12|JAN-DEC], day of week
 * [1-7|MON-SUN], and an optional year.
 *
 * @link http://en.wikipedia.org/wiki/Cron
 */
class CronExpression
{
    const SECOND = 0;
    const MINUTE = 1;
    const HOUR = 2;
    const DAY = 3;
    const MONTH = 4;
    const WEEKDAY = 5;
    const YEAR = 6;

    /**
     * @var array CRON expression parts
     */
    private array $cronParts;

    /**
     * @var FieldFactory|null CRON field factory
     */
    private ?FieldFactory $fieldFactory;

    /**
     * @var int Max iteration count when searching for next run date
     */
    private int $maxIterationCount = 1000;

    /**
     * @var array Order in which to test of cron parts
     */
    private static array $order = [
        self::YEAR,
        self::MONTH,
        self::DAY,
        self::WEEKDAY,
        self::HOUR,
        self::MINUTE,
        self::SECOND
    ];

    /**
     * Factory method to create a new CronExpression.
     *
     * @param string $expression The CRON expression to create.  There are
     *                           several special predefined values which can be used to substitute the
     *                           CRON expression:
     *
     *      `@yearly`, `@annually` - Run once a year, midnight, Jan. 1 - 0 0 1 1 *
     *      `@monthly` - Run once a month, midnight, first of month - 0 0 1 * *
     *      `@weekly` - Run once a week, midnight on Sun - 0 0 * * 0
     *      `@daily` - Run once a day, midnight - 0 0 * * *
     *      `@hourly` - Run once an hour, first minute - 0 * * * *
     * @param FieldFactory|null $fieldFactory Field factory to use
     *
     * @return CronExpression
     */
    public static function factory(string $expression, FieldFactory $fieldFactory = null): CronExpression
    {
        $mappings = array(
            '@yearly' => '0 0 0 1 1 *',
            '@annually' => '0 0 0 1 1 *',
            '@monthly' => '0 0 0 1 * *',
            '@weekly' => '0 0 0 * * 0',
            '@daily' => '0 0 0 * * *',
            '@hourly' => '0 0 * * * *',
            '@minutely' => '0 * * * * *',
            '@secondly' => '* * * * * *'
        );

        if (isset($mappings[$expression])) {
            $expression = $mappings[$expression];
        }
        $expression = stripslashes($expression);

        return new static($expression, $fieldFactory ?: new FieldFactory());
    }

    /**
     * Validate a CronExpression.
     *
     * @param string $expression The CRON expression to validate.
     *
     * @return bool True if a valid CRON expression was passed. False if not.
     */
    public static function isValidExpression(string $expression): bool
    {
        try {
            self::factory($expression);
        } catch (InvalidArgumentException $e) {
            return false;
        }

        return true;
    }

    /**
     * Parse a CRON expression
     *
     * @param string $expression CRON expression (e.g. '8 * * * * *')
     * @param FieldFactory|null $fieldFactory Factory to create cron fields
     */
    public function __construct(string $expression, ?FieldFactory $fieldFactory = null)
    {
        $this->fieldFactory = $fieldFactory;
        $this->setExpression($expression);
    }

    /**
     * Set or change the CRON expression
     *
     * @param string $value CRON expression (e.g. 8 * * * * *)
     *
     * @return CronExpression
     * @throws \InvalidArgumentException if not a valid CRON expression
     */
    public function setExpression(string $value): CronExpression
    {
        $this->cronParts = preg_split('/\s/', $value, -1, PREG_SPLIT_NO_EMPTY);
        if (count($this->cronParts) < 6) {
            throw new InvalidArgumentException(
                $value . ' is not a valid CRON expression'
            );
        }

        foreach ($this->cronParts as $position => $part) {
            $this->setPart($position, $part);
        }

        return $this;
    }

    /**
     * Set part of the CRON expression
     *
     * @param int $position The position of the CRON expression to set
     * @param string $value The value to set
     *
     * @return CronExpression
     * @throws \InvalidArgumentException if the value is not valid for the part
     */
    public function setPart(int $position, string $value): CronExpression
    {
        if (!$this->fieldFactory->getField($position)->validate($value)) {
            throw new InvalidArgumentException(
                'Invalid CRON field value ' . $value . ' at position ' . $position
            );
        }

        $this->cronParts[$position] = $value;

        return $this;
    }

    /**
     * Set max iteration count for searching next run dates
     *
     * @param int $maxIterationCount Max iteration count when searching for next run date
     *
     * @return CronExpression
     */
    public function setMaxIterationCount(int $maxIterationCount): CronExpression
    {
        $this->maxIterationCount = $maxIterationCount;

        return $this;
    }

    /**
     * Get a next run date relative to the current date or a specific date
     *
     * @param string|\DateTimeInterface $currentTime Relative calculation date
     * @param int $nth Number of matches to skip before returning a
     *                                                    matching next run date.  0, the default, will return the
     *                                                    current date and time if the next run date falls on the
     *                                                    current date and time.  Setting this value to 1 will
     *                                                    skip the first match and go to the second match.
     *                                                    Setting this value to 2 will skip the first 2
     *                                                    matches and so on.
     * @param bool $allowCurrentDate Set to TRUE to return the current date if
     *                                                    it matches the cron expression.
     * @param string|null $timeZone TimeZone to use instead of the system default
     *
     * @return \DateTime
     * @throws \RuntimeException on too many iterations
     * @throws Exception
     */
    public function getNextRunDate($currentTime = 'now', int $nth = 0, bool $allowCurrentDate = false, ?string $timeZone = null): DateTime
    {
        return $this->getRunDate($currentTime, $nth, false, $allowCurrentDate, $timeZone);
    }

    /**
     * Get a previous run date relative to the current date or a specific date
     *
     * @param string|\DateTimeInterface $currentTime Relative calculation date
     * @param int $nth Number of matches to skip before returning
     * @param bool $allowCurrentDate Set to TRUE to return the
     *                                                    current date if it matches the cron expression
     * @param string|null $timeZone TimeZone to use instead of the system default
     *
     * @return \DateTime
     * @throws \RuntimeException|Exception on too many iterations
     */
    public function getPreviousRunDate($currentTime = 'now', int $nth = 0, bool $allowCurrentDate = false, ?string $timeZone = null): DateTime
    {
        return $this->getRunDate($currentTime, $nth, true, $allowCurrentDate, $timeZone);
    }

    /**
     * Get multiple run dates starting at the current date or a specific date
     *
     * @param int $total Set the total number of dates to calculate
     * @param string|\DateTimeInterface $currentTime Relative calculation date
     * @param bool $invert Set to TRUE to retrieve previous dates
     * @param bool $allowCurrentDate Set to TRUE to return the
     *                                                    current date if it matches the cron expression
     * @param string|null $timeZone TimeZone to use instead of the system default
     *
     * @return \DateTime[] Returns an array of run dates
     * @throws Exception
     */
    public function getMultipleRunDates(int $total, $currentTime = 'now', bool $invert = false, bool $allowCurrentDate = false, string $timeZone = null): array
    {
        $matches = array();
        for ($i = 0; $i < max(0, $total); $i++) {
            try {
                $matches[] = $this->getRunDate($currentTime, $i, $invert, $allowCurrentDate, $timeZone);
            } catch (RuntimeException $e) {
                break;
            }
        }

        return $matches;
    }

    /**
     * Get all or part of the CRON expression
     *
     * @param string|null $part Specify the part to retrieve or NULL to get the full
     *                     cron schedule string.
     *
     * @return string|null Returns the CRON expression, a part of the
     *                     CRON expression, or NULL if the part was specified but not found
     */
    public function getExpression(string $part = null): ?string
    {
        if (null === $part) {
            return implode(' ', $this->cronParts);
        } elseif (array_key_exists($part, $this->cronParts)) {
            return $this->cronParts[$part];
        }

        return null;
    }

    /**
     * Helper method to output the full expression.
     *
     * @return string Full CRON expression
     */
    public function __toString()
    {
        return $this->getExpression();
    }

    /**
     * Determine if the cron is due to run based on the current date or a
     * specific date.  This method assumes that the current number of
     * seconds are irrelevant, and should be called once per minute.
     *
     * @param string|\DateTimeInterface $currentTime Relative calculation date
     * @param string|null $timeZone TimeZone to use instead of the system default
     *
     * @return bool Returns TRUE if the cron is due to run or FALSE if not
     * @throws Exception
     */
    public function isDue($currentTime = 'now', string $timeZone = null): bool
    {
        $timeZone = $this->determineTimeZone($currentTime, $timeZone);

        if ('now' === $currentTime) {
            $currentTime = new DateTime();
        } elseif ($currentTime instanceof DateTime) {
            //
        } elseif ($currentTime instanceof DateTimeImmutable) {
            $currentTime = DateTime::createFromFormat('U', $currentTime->format('U'));
        } else {
            $currentTime = new DateTime($currentTime);
        }
        $currentTime->setTimezone(new DateTimeZone($timeZone));

        // drop the seconds to 0
        $currentTime = DateTime::createFromFormat('Y-m-d H:i:s', $currentTime->format('Y-m-d H:i:s'));

        try {
            return $this->getNextRunDate($currentTime, 0, true)->getTimestamp() === $currentTime->getTimestamp();
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Get the next or previous run date of the expression relative to a date
     *
     * @param string|\DateTimeInterface $currentTime Relative calculation date
     * @param int $nth Number of matches to skip before returning
     * @param bool $invert Set to TRUE to go backwards in time
     * @param bool $allowCurrentDate Set to TRUE to return the
     *                                                    current date if it matches the cron expression
     * @param string|null $timeZone TimeZone to use instead of the system default
     *
     * @return \DateTime
     * @throws \RuntimeException on too many iterations
     * @throws Exception
     */
    protected function getRunDate($currentTime = null, int $nth = 0, bool $invert = false, bool $allowCurrentDate = false, string $timeZone = null): DateTime
    {
        $timeZone = $this->determineTimeZone($currentTime, $timeZone);

        if ($currentTime instanceof DateTime) {
            $currentDate = clone $currentTime;
        } elseif ($currentTime instanceof DateTimeImmutable) {
            $currentDate = DateTime::createFromFormat('U', $currentTime->format('U'));
        } else {
            $currentDate = new DateTime($currentTime ?: 'now');
        }

        $currentDate->setTimezone(new DateTimeZone($timeZone));
        $currentDate->setTime($currentDate->format('H'), $currentDate->format('i'), $currentDate->format('s'));
        $nextRun = clone $currentDate;

        // We don't have to satisfy * or null fields
        $parts = array();
        $fields = array();
        foreach (self::$order as $position) {
            $part = $this->getExpression($position);
            if (null === $part || '*' === $part) {
                continue;
            }
            $parts[$position] = $part;
            $fields[$position] = $this->fieldFactory->getField($position);
        }

        // Set a hard limit to bail on an impossible date
        for ($i = 0; $i < $this->maxIterationCount; $i++) {

            foreach ($parts as $position => $part) {
                $satisfied = false;
                // Get the field object used to validate this part
                $field = $fields[$position];
                // Check if this is singular or a list
                if (strpos($part, ',') === false) {
                    $satisfied = $field->isSatisfiedBy($nextRun, $part);
                } else {
                    foreach (array_map('trim', explode(',', $part)) as $listPart) {
                        if ($field->isSatisfiedBy($nextRun, $listPart)) {
                            $satisfied = true;
                            break;
                        }
                    }
                }

                // If the field is not satisfied, then start over
                if (!$satisfied) {
                    $field->increment($nextRun, $invert, $part);
                    continue 2;
                }
            }

            // Skip this match if needed
            if ((!$allowCurrentDate && $nextRun == $currentDate) || --$nth > -1) {
                $this->fieldFactory->getField(0)->increment($nextRun, $invert, $parts[0] ?? null);
                continue;
            }

            return $nextRun;
        }

        // @codeCoverageIgnoreStart
        throw new RuntimeException('Impossible CRON expression');
        // @codeCoverageIgnoreEnd
    }

    /**
     * Workout what timeZone should be used.
     *
     * @param string|\DateTimeInterface $currentTime Relative calculation date
     * @param string|null $timeZone TimeZone to use instead of the system default
     *
     * @return string
     */
    protected function determineTimeZone($currentTime, ?string $timeZone = null): string
    {
        if (!is_null($timeZone)) {
            return $timeZone;
        }

        if ($currentTime instanceof DateTimeInterface) {
            return $currentTime->getTimeZone()->getName();
        }

        return date_default_timezone_get();
    }
}