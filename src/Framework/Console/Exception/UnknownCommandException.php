<?php
/**
 * @link http://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license http://www.yiiframework.com/license/
 */

namespace Yew\Framework\Console\Exception;

use Yew\Framework\Console\Application;

/**
 * UnknownCommandException represents an exception caused by incorrect usage of a console command.
 *
 * @author Carsten Brandt <mail@cebe.cc>
 * @since 2.0.11
 */
class UnknownCommandException extends Exception
{
    /**
     * @var string the name of the command that could not be recognized.
     */
    public string $command;

    /**
     * @var Application
     */
    protected Application $application;


    /**
     * Construct the exception.
     *
     * @param string $route the route of the command that could not be found.
     * @param Application $application the console application instance involved.
     * @param int $code the Exception code.
     * @param \Exception|null $previous the previous exception used for the exception chaining.
     */
    public function __construct($route, $application, $code = 0, ?\Exception $previous = null)
    {
        $this->command = $route;
        $this->application = $application;
        parent::__construct("Unknown command \"$route\".", $code, $previous);
    }

    /**
     * @return string the user-friendly name of this exception
     */
    public function getName(): string
    {
        return 'Unknown command';
    }
}
