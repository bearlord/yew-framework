<?php
/**
 * @link http://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license http://www.yiiframework.com/license/
 */

namespace Yew\Framework\Validators;

use DateTime;
use IntlDateFormatter;
use Yew\Yew;
use Yew\Framework\Exception\InvalidConfigException;
use Yew\Framework\Helpers\FormatConverter;

/**
 * DateValidator verifies if the attribute represents a date, time or datetime in a proper [[format]].
 *
 * It can also parse internationalized dates in a specific [[locale]] like e.g. `12 мая 2014` when [[format]]
 * is configured to use a time pattern in ICU format.
 *
 * It is further possible to limit the date within a certain range using [[min]] and [[max]].
 *
 * Additional to validating the date it can also export the parsed timestamp as a machine readable format
 * which can be configured using [[timestampAttribute]]. For values that include time information (not date-only values)
 * also the time zone will be adjusted. The time zone of the input value is assumed to be the one specified by the [[timeZone]]
 * property and the target timeZone will be UTC when [[timestampAttributeFormat]] is `null` (exporting as UNIX timestamp)
 * or [[timestampAttributeTimeZone]] otherwise. If you want to avoid the time zone conversion, make sure that [[timeZone]] and
 * [[timestampAttributeTimeZone]] are the same.
 *
 * @author Qiang Xue <qiang.xue@gmail.com>
 * @author Carsten Brandt <mail@cebe.cc>
 * @since 2.0
 */
class DateValidator extends Validator
{
    /**
     * Constant for specifying the validation [[type]] as a date value, used for validation with intl short format.
     * @since 2.0.8
     * @see type
     */
    const TYPE_DATE = 'date';
    /**
     * Constant for specifying the validation [[type]] as a datetime value, used for validation with intl short format.
     * @since 2.0.8
     * @see type
     */
    const TYPE_DATETIME = 'datetime';
    /**
     * Constant for specifying the validation [[type]] as a time value, used for validation with intl short format.
     * @since 2.0.8
     * @see type
     */
    const TYPE_TIME = 'time';

    /**
     * @var string the type of the validator. Indicates, whether a date, time or datetime value should be validated.
     * This property influences the default value of [[format]] and also sets the correct behavior when [[format]] is one of the intl
     * short formats, `short`, `medium`, `long`, or `full`.
     *
     * This is only effective when the [PHP intl extension](https://secure.php.net/manual/en/book.intl.php) is installed.
     *
     * This property can be set to the following values:
     *
     * - [[TYPE_DATE]] - (default) for validating date values only, that means only values that do not include a time range are valid.
     * - [[TYPE_DATETIME]] - for validating datetime values, that contain a date part as well as a time part.
     * - [[TYPE_TIME]] - for validating time values, that contain no date information.
     *
     * @since 2.0.8
     */
    public $type = self::TYPE_DATE;
    /**
     * @var string the date format that the value being validated should follow.
     * This can be a date time pattern as described in the [ICU manual](http://userguide.icu-project.org/formatparse/datetime#TOC-Date-Time-Format-Syntax).
     *
     * Alternatively this can be a string prefixed with `php:` representing a format that can be recognized by the PHP Datetime class.
     * Please refer to <https://secure.php.net/manual/en/datetime.createfromformat.php> on supported formats.
     *
     * If this property is not set, the default value will be obtained from `Yew::$app->formatter->dateFormat`, see [[\Yew\Framework\I18n\Formatter::dateFormat]] for details.
     * Since version 2.0.8 the default value will be determined from different formats of the formatter class,
     * dependent on the value of [[type]]:
     *
     * - if type is [[TYPE_DATE]], the default value will be taken from [[\Yew\Framework\I18n\Formatter::dateFormat]],
     * - if type is [[TYPE_DATETIME]], it will be taken from [[\Yew\Framework\I18n\Formatter::datetimeFormat]],
     * - and if type is [[TYPE_TIME]], it will be [[\Yew\Framework\I18n\Formatter::timeFormat]].
     *
     * Here are some example values:
     *
     * ```php
     * 'MM/dd/yyyy' // date in ICU format
     * 'php:m/d/Y' // the same date in PHP format
     * 'MM/dd/yyyy HH:mm' // not only dates but also times can be validated
     * ```
     *
     * **Note:** the underlying date parsers being used vary dependent on the format. If you use the ICU format and
     * the [PHP intl extension](https://secure.php.net/manual/en/book.intl.php) is installed, the [IntlDateFormatter](https://secure.php.net/manual/en/intldateformatter.parse.php)
     * is used to parse the input value. In all other cases the PHP [DateTime](https://secure.php.net/manual/en/datetime.createfromformat.php) class
     * is used. The IntlDateFormatter has the advantage that it can parse international dates like `12. Mai 2015` or `12 мая 2014`, while the
     * PHP parser is limited to English only. The PHP parser however is more strict about the input format as it will not accept
     * `12.05.05` for the format `php:d.m.Y`, but the IntlDateFormatter will accept it for the format `dd.MM.yyyy`.
     * If you need to use the IntlDateFormatter you can avoid this problem by specifying a [[min|minimum date]].
     */
    public $format;
    /**
     * @var string the locale ID that is used to localize the date parsing.
     * This is only effective when the [PHP intl extension](https://secure.php.net/manual/en/book.intl.php) is installed.
     * If not set, the locale of the [[\Yew\Framework\Base\Application::formatter|formatter]] will be used.
     * See also [[\Yew\Framework\I18n\Formatter::locale]].
     */
    public $locale;
    /**
     * @var string the timezone to use for parsing date and time values.
     * This can be any value that may be passed to [date_default_timezone_set()](https://secure.php.net/manual/en/function.date-default-timezone-set.php)
     * e.g. `UTC`, `Europe/Berlin` or `America/Chicago`.
     * Refer to the [php manual](https://secure.php.net/manual/en/timezones.php) for available timezones.
     * If this property is not set, [[\Yew\Framework\Base\Application::timeZone]] will be used.
     */
    public $timeZone;
    /**
     * @var string the name of the attribute to receive the parsing result.
     * When this property is not null and the validation is successful, the named attribute will
     * receive the parsing result.
     *
     * This can be the same attribute as the one being validated. If this is the case,
     * the original value will be overwritten with the timestamp value after successful validation.
     *
     * Note, that when using this property, the input value will be converted to a unix timestamp,
     * which by definition is in UTC, so a conversion from the [[$timeZone|input time zone]] to UTC
     * will be performed. When defining [[$timestampAttributeFormat]] you can control the conversion by
     * setting [[$timestampAttributeTimeZone]] to a different value than `'UTC'`.
     *
     * @see timestampAttributeFormat
     * @see timestampAttributeTimeZone
     */
    public $timestampAttribute;
    /**
     * @var string the format to use when populating the [[timestampAttribute]].
     * The format can be specified in the same way as for [[format]].
     *
     * If not set, [[timestampAttribute]] will receive a UNIX timestamp.
     * If [[timestampAttribute]] is not set, this property will be ignored.
     * @see format
     * @see timestampAttribute
     * @since 2.0.4
     */
    public $timestampAttributeFormat;
    /**
     * @var string the timezone to use when populating the [[timestampAttribute]]. Defaults to `UTC`.
     *
     * This can be any value that may be passed to [date_default_timezone_set()](https://secure.php.net/manual/en/function.date-default-timezone-set.php)
     * e.g. `UTC`, `Europe/Berlin` or `America/Chicago`.
     * Refer to the [php manual](https://secure.php.net/manual/en/timezones.php) for available timezones.
     *
     * If [[timestampAttributeFormat]] is not set, this property will be ignored.
     * @see timestampAttributeFormat
     * @since 2.0.4
     */
    public $timestampAttributeTimeZone = 'UTC';
    /**
     * @var int|string upper limit of the date. Defaults to null, meaning no upper limit.
     * This can be a unix timestamp or a string representing a date time value.
     * If this property is a string, [[format]] will be used to parse it.
     * @see tooBig for the customized message used when the date is too big.
     * @since 2.0.4
     */
    public $max;
    /**
     * @var int|string lower limit of the date. Defaults to null, meaning no lower limit.
     * This can be a unix timestamp or a string representing a date time value.
     * If this property is a string, [[format]] will be used to parse it.
     * @see tooSmall for the customized message used when the date is too small.
     * @since 2.0.4
     */
    public $min;
    /**
     * @var string user-defined error message used when the value is bigger than [[max]].
     * @since 2.0.4
     */
    public $tooBig;
    /**
     * @var string user-defined error message used when the value is smaller than [[min]].
     * @since 2.0.4
     */
    public $tooSmall;
    /**
     * @var string user friendly value of upper limit to display in the error message.
     * If this property is null, the value of [[max]] will be used (before parsing).
     * @since 2.0.4
     */
    public $maxString;
    /**
     * @var string user friendly value of lower limit to display in the error message.
     * If this property is null, the value of [[min]] will be used (before parsing).
     * @since 2.0.4
     */
    public $minString;

    /**
     * @var array map of short format names to IntlDateFormatter constant values.
     */
    private $_dateFormats = [
        'short' => 3, // IntlDateFormatter::SHORT,
        'medium' => 2, // IntlDateFormatter::MEDIUM,
        'long' => 1, // IntlDateFormatter::LONG,
        'full' => 0, // IntlDateFormatter::FULL,
    ];


    /**
     * {@inheritdoc}
     */
    public function init()
    {
        parent::init();
        if ($this->message === null) {
            $this->message = Yew::t('yew', 'The format of {attribute} is invalid.');
        }
        if ($this->format === null) {
            if ($this->type === self::TYPE_DATE) {
                $this->format = Yew::$app->formatter->dateFormat;
            } elseif ($this->type === self::TYPE_DATETIME) {
                $this->format = Yew::$app->formatter->datetimeFormat;
            } elseif ($this->type === self::TYPE_TIME) {
                $this->format = Yew::$app->formatter->timeFormat;
            } else {
                throw new InvalidConfigException('Unknown validation type set for DateValidator::$type: ' . $this->type);
            }
        }

        if ($this->locale === null) {
            $this->locale = Yew::$app->getContextLanguage();
        }
        if ($this->timeZone === null) {
            $this->timeZone = Yew::$app->getContextTimeZone();
        }
        if ($this->min !== null && $this->tooSmall === null) {
            $this->tooSmall = Yew::t('yew', '{attribute} must be no less than {min}.');
        }
        if ($this->max !== null && $this->tooBig === null) {
            $this->tooBig = Yew::t('yew', '{attribute} must be no greater than {max}.');
        }
        if ($this->maxString === null) {
            $this->maxString = (string) $this->max;
        }
        if ($this->minString === null) {
            $this->minString = (string) $this->min;
        }
        if ($this->max !== null && is_string($this->max)) {
            $timestamp = $this->parseDateValue($this->max);
            if ($timestamp === false) {
                throw new InvalidConfigException("Invalid max date value: {$this->max}");
            }
            $this->max = $timestamp;
        }
        if ($this->min !== null && is_string($this->min)) {
            $timestamp = $this->parseDateValue($this->min);
            if ($timestamp === false) {
                throw new InvalidConfigException("Invalid min date value: {$this->min}");
            }
            $this->min = $timestamp;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function validateAttribute($model, $attribute)
    {
        $value = $model->$attribute;
        if ($this->isEmpty($value)) {
            if ($this->timestampAttribute !== null) {
                $model->{$this->timestampAttribute} = null;
            }
            return;
        }

        $timestamp = $this->parseDateValue($value);
        if ($timestamp === false) {
            if ($this->timestampAttribute === $attribute) {
                if ($this->timestampAttributeFormat === null) {
                    if (is_int($value)) {
                        return;
                    }
                } else {
                    if ($this->parseDateValueFormat($value, $this->timestampAttributeFormat) !== false) {
                        return;
                    }
                }
            }
            $this->addError($model, $attribute, $this->message, []);
        } elseif ($this->min !== null && $timestamp < $this->min) {
            $this->addError($model, $attribute, $this->tooSmall, ['min' => $this->minString]);
        } elseif ($this->max !== null && $timestamp > $this->max) {
            $this->addError($model, $attribute, $this->tooBig, ['max' => $this->maxString]);
        } elseif ($this->timestampAttribute !== null) {
            if ($this->timestampAttributeFormat === null) {
                $model->{$this->timestampAttribute} = $timestamp;
            } else {
                $model->{$this->timestampAttribute} = $this->formatTimestamp($timestamp, $this->timestampAttributeFormat);
            }
        }
    }

    /**
     * {@inheritdoc}
     */
    protected function validateValue($value)
    {
        $timestamp = $this->parseDateValue($value);
        if ($timestamp === false) {
            $this->setValidCode(300001);
            return [$this->message, [], $this->getValidCode()];
        } elseif ($this->min !== null && $timestamp < $this->min) {
            $this->setValidCode(300002);
            return [$this->tooSmall, ['min' => $this->minString], $this->getValidCode()];
        } elseif ($this->max !== null && $timestamp > $this->max) {
            $this->setValidCode(300003);
            return [$this->tooBig, ['max' => $this->maxString], $this->getValidCode()];
        }

        return null;
    }

    /**
     * Parses date string into UNIX timestamp.
     *
     * @param string $value string representing date
     * @return int|false a UNIX timestamp or `false` on failure.
     */
    protected function parseDateValue($value)
    {
        // TODO consider merging these methods into single one at 2.1
        return $this->parseDateValueFormat($value, $this->format);
    }

    /**
     * Parses date string into UNIX timestamp.
     *
     * @param string $value string representing date
     * @param string $format expected date format
     * @return int|false a UNIX timestamp or `false` on failure.
     */
    private function parseDateValueFormat($value, $format)
    {
        if (is_array($value)) {
            return false;
        }
        if (strncmp($format, 'php:', 4) === 0) {
            $format = substr($format, 4);
        } else {
            if (extension_loaded('intl')) {
                return $this->parseDateValueIntl($value, $format);
            }

            // fallback to PHP if intl is not installed
            $format = FormatConverter::convertDateIcuToPhp($format, 'date');
        }

        return $this->parseDateValuePHP($value, $format);
    }

    /**
     * Parses a date value using the IntlDateFormatter::parse().
     * @param string $value string representing date
     * @param string $format the expected date format
     * @return int|bool a UNIX timestamp or `false` on failure.
     * @throws InvalidConfigException
     */
    private function parseDateValueIntl($value, $format)
    {
        if (isset($this->_dateFormats[$format])) {
            if ($this->type === self::TYPE_DATE) {
                $formatter = new IntlDateFormatter($this->locale, $this->_dateFormats[$format], IntlDateFormatter::NONE, 'UTC');
            } elseif ($this->type === self::TYPE_DATETIME) {
                $formatter = new IntlDateFormatter($this->locale, $this->_dateFormats[$format], $this->_dateFormats[$format], $this->timeZone);
            } elseif ($this->type === self::TYPE_TIME) {
                $formatter = new IntlDateFormatter($this->locale, IntlDateFormatter::NONE, $this->_dateFormats[$format], $this->timeZone);
            } else {
                throw new InvalidConfigException('Unknown validation type set for DateValidator::$type: ' . $this->type);
            }
        } else {
            // if no time was provided in the format string set time to 0 to get a simple date timestamp
            $hasTimeInfo = (strpbrk($format, 'ahHkKmsSA') !== false);
            $formatter = new IntlDateFormatter($this->locale, IntlDateFormatter::NONE, IntlDateFormatter::NONE, $hasTimeInfo ? $this->timeZone : 'UTC', null, $format);
        }
        // enable strict parsing to avoid getting invalid date values
        $formatter->setLenient(false);

        // There should not be a warning thrown by parse() but this seems to be the case on windows so we suppress it here
        // See https://github.com/yiisoft/yii2/issues/5962 and https://bugs.php.net/bug.php?id=68528
        $parsePos = 0;
        $parsedDate = @$formatter->parse($value, $parsePos);
        if ($parsedDate === false || $parsePos !== mb_strlen($value, Yew::$app ? Yew::$app->charset : 'UTF-8')) {
            return false;
        }

        return $parsedDate;
    }

    /**
     * Parses a date value using the DateTime::createFromFormat().
     * @param string $value string representing date
     * @param string $format the expected date format
     * @return int|bool a UNIX timestamp or `false` on failure.
     */
    private function parseDateValuePHP($value, $format)
    {
        // if no time was provided in the format string set time to 0 to get a simple date timestamp
        $hasTimeInfo = (strpbrk($format, 'HhGgisU') !== false);

        $date = DateTime::createFromFormat($format, $value, new \DateTimeZone($hasTimeInfo ? $this->timeZone : 'UTC'));
        $errors = DateTime::getLastErrors();
        if ($date === false || $errors['error_count'] || $errors['warning_count']) {
            return false;
        }

        if (!$hasTimeInfo) {
            $date->setTime(0, 0, 0);
        }

        return $date->getTimestamp();
    }

    /**
     * Formats a timestamp using the specified format.
     * @param int $timestamp
     * @param string $format
     * @return string
     */
    private function formatTimestamp($timestamp, $format)
    {
        if (strncmp($format, 'php:', 4) === 0) {
            $format = substr($format, 4);
        } else {
            $format = FormatConverter::convertDateIcuToPhp($format, 'date');
        }

        $date = new DateTime();
        $date->setTimestamp($timestamp);
        $date->setTimezone(new \DateTimeZone($this->timestampAttributeTimeZone));
        return $date->format($format);
    }
}
