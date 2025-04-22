<?php
/**
 * Yew framework
 * @author bearlord <565364226@qq.com>
 */

namespace Yew\Core\Plugins\Logger;


class LoggerExtra
{
    /**
     * @var array
     */
    private $context = "";

    /**
     * Get
     * @return LoggerExtra|mixed
     */
    public static function get()
    {
        $result = getDeepContextValueByClassName(LoggerExtra::class);
        if ($result == null) {
            $result = new LoggerExtra();
            setContextValue("LoggerExtra", $result);
        }
        return $result;
    }

    /**
     * Add context
     *
     * @param string $key
     * @param $value
     */
    public function addContext(string $key, $value)
    {
        if (empty($this->context)) $this->context = [];

        $this->context[$key] = $value;
    }

    /**
     * Get context
     *
     * @return array
     */
    public function getContext()
    {
        if (is_array($this->context)) {
            return array_values($this->context);
        }
        return $this->context;
    }
}