<?php
/**
 * @link http://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license http://www.yiiframework.com/license/
 */

namespace Yew\Framework\I18n;

use Yew\Yew;
use Yew\Framework\Base\Component;
use Yew\Framework\Exception\InvalidConfigException;

/**
 * Locale provides various locale information via convenient methods.
 *
 * The class requires [PHP intl extension](https://secure.php.net/manual/en/book.intl.php) to be installed.
 *
 * @property string $currencySymbol This property is read-only.
 *
 * @since 2.0.14
 */
class Locale extends Component
{
    /**
     * @var string the locale ID.
     * If not set, [[\Yew\FrameworkBase\Application::language]] will be used.
     */
    public $locale;


    /**
     * {@inheritdoc}
     */
    public function init()
    {
        if (!extension_loaded('intl')) {
            throw new InvalidConfigException('Locale component requires PHP intl extension to be installed.');
        }

        if ($this->locale === null) {
            $this->locale = Yew::$app->getla;
        }
    }

    /**
     * Returns a currency symbol
     *
     * @param string $currencyCode the 3-letter ISO 4217 currency code to get symbol for. If null,
     * method will attempt using currency code from [[locale]].
     * @return string
     */
    public function getCurrencySymbol($currencyCode = null)
    {
        $locale = $this->locale;

        if ($currencyCode !== null) {
            $locale .= '@currency=' . $currencyCode;
        }

        $formatter = new \NumberFormatter($locale, \NumberFormatter::CURRENCY);
        return $formatter->getSymbol(\NumberFormatter::CURRENCY_SYMBOL);
    }
}
