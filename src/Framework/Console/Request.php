<?php
/**
 * @link http://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license http://www.yiiframework.com/license/
 */

namespace Yew\Framework\Console;

/**
 * The console Request represents the environment information for a console application.
 *
 * It is a wrapper for the PHP `$_SERVER` variable which holds information about the
 * currently running PHP script and the command line arguments given to it.
 *
 * @property array $params The command line arguments. It does not include the entry script name.
 *
 * @author Qiang Xue <qiang.xue@gmail.com>
 * @since 2.0
 */
class Request extends \Yew\Framework\Base\Request
{
    private ?array $_params = null;


    /**
     * Returns the command line arguments.
     * @return array|null the command line arguments. It does not include the entry script name.
     */
    public function getParams(): ?array
    {
        if ($this->_params === null) {
            if (isset($_SERVER['argv'])) {
                $this->_params = $_SERVER['argv'];
                array_shift($this->_params);
            } else {
                $this->_params = [];
            }
        }

        return $this->_params;
    }

    /**
     * Sets the command line arguments.
     * @param array|null $params the command line arguments
     */
    public function setParams(?array $params)
    {
        $this->_params = $params;
    }

    /**
     * Resolves the current request into a route and the associated parameters.
     * @return array the first element is the route, and the second is the associated parameters.
     * @throws Exception when parameter is wrong and can not be resolved
     */
    public function resolve(): array
    {
        $rawParams = $this->getParams();
        $endOfOptionsFound = false;
        if (isset($rawParams[0])) {
            $route = array_shift($rawParams);

            if ($route === '--') {
                $endOfOptionsFound = true;
                $route = array_shift($rawParams);
            }
        } else {
            $route = '';
        }

        $params = [];
        $prevOption = null;
        foreach ($rawParams as $param) {
            if ($endOfOptionsFound) {
                $params[] = $param;
            } elseif ($param === '--') {
                $endOfOptionsFound = true;
            } elseif (preg_match('/^--([\w-]+)(?:=(.*))?$/', $param, $matches)) {
                $name = $matches[1];
                if (is_numeric(substr($name, 0, 1))) {
                    throw new Exception('Parameter "' . $name . '" is not valid');
                }

                if ($name !== Application::OPTION_APPCONFIG) {
                    $params[$name] = isset($matches[2]) ? $matches[2] : true;
                    $prevOption = &$params[$name];
                }
            } elseif (preg_match('/^-([\w-]+)(?:=(.*))?$/', $param, $matches)) {
                $name = $matches[1];
                if (is_numeric($name)) {
                    $params[] = $param;
                } else {
                    $params['_aliases'][$name] = isset($matches[2]) ? $matches[2] : true;
                    $prevOption = &$params['_aliases'][$name];
                }
            } elseif ($prevOption === true) {
                // `--option value` syntax
                $prevOption = $param;
            } else {
                $params[] = $param;
            }
        }

        return [$route, $params];
    }
}
