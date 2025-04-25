<?php
/**
 * Copied from hyperf, and modifications are not listed anymore.
 * @contact  group@hyperf.io
 * @licence  MIT License
 * @license  https://github.com/hyperf/hyperf/blob/master/LICENSE
 */

namespace Yew\Parallel;

class Exception extends \RuntimeException
{
    /**
     * @var array
     */
    private array $results;

    /**
     * @var array
     */
    private array $throwables;

    /**
     * @return array
     */
    public function getResults(): array
    {
        return $this->results;
    }

    /**
     * @param array $results
     */
    public function setResults(array $results)
    {
        $this->results = $results;
    }

    /**
     * @return array
     */
    public function getThrowables(): array
    {
        return $this->throwables;
    }

    /**
     * @param array $throwables
     * @return array
     */
    public function setThrowables(array $throwables): array
    {
        return $this->throwables = $throwables;
    }
}