<?php
/**
 * @link http://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license http://www.yiiframework.com/license/
 */

namespace Yew\Framework\Db\Pgsql;

/**
 * The class converts PostgreSQL array representation to PHP array
 *
 * @author Sergei Tigrov <rrr-r@ya.ru>
 * @author Dmytro Naumenko <d.naumenko.a@gmail.com>
 * @since 2.0.14
 */
class ArrayParser
{
    /**
     * @var string Character used in array
     */
    private string $delimiter = ',';


    /**
     * Convert array from PostgreSQL to PHP
     *
     * @param string|null $value string to be converted
     * @return array|null
     */
    public function parse(?string $value = null): ?array
    {
        if ($value === null) {
            return null;
        }

        if ($value === '{}') {
            return [];
        }

        return $this->parseArray($value);
    }

    /**
     * Pares PgSQL array encoded in string
     *
     * @param string $value
     * @param int $i parse starting position
     * @return array
     */
    private function parseArray(string $value, int &$i = 0): array
    {
        $result = [];
        $len = strlen($value);
        for (++$i; $i < $len; ++$i) {
            switch ($value[$i]) {
                case '{':
                    $result[] = $this->parseArray($value, $i);
                    break;
                case '}':
                    break 2;
                case $this->delimiter:
                    if (empty($result)) { // `{}` case
                        $result[] = null;
                    }
                    if (in_array($value[$i + 1], [$this->delimiter, '}'], true)) { // `{,}` case
                        $result[] = null;
                    }
                    break;
                default:
                    $result[] = $this->parseString($value, $i);
            }
        }

        return $result;
    }

    /**
     * Parses PgSQL encoded string
     *
     * @param string $value
     * @param int $i parse starting position
     * @return null|string
     */
    private function parseString(string $value, int &$i): ?string
    {
        $isQuoted = $value[$i] === '"';
        $stringEndChars = $isQuoted ? ['"'] : [$this->delimiter, '}'];
        $result = '';
        $len = strlen($value);
        for ($i += $isQuoted ? 1 : 0; $i < $len; ++$i) {
            if (in_array($value[$i], ['\\', '"'], true) && in_array($value[$i + 1], [$value[$i], '"'], true)) {
                ++$i;
            } elseif (in_array($value[$i], $stringEndChars, true)) {
                break;
            }

            $result .= $value[$i];
        }

        $i -= $isQuoted ? 0 : 1;

        if (!$isQuoted && $result === 'NULL') {
            $result = null;
        }

        return $result;
    }
}
