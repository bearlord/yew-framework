<?php
/**
 * @link http://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license http://www.yiiframework.com/license/
 */

namespace Yew\Framework\Base;

use Swoole\ExitException;
use Yew\Core\Server\Server;
use Yew\Framework\Console\Exception\Exception;
use Yew\Framework\Exception\ErrorException;
use Yew\Framework\Exception\UserException;
use Yew\Yew;
use Yew\Framework\Helpers\VarDumper;
use Yew\Framework\Web\HttpException;

/**
 * ErrorHandler handles uncaught PHP errors and exceptions.
 *
 * ErrorHandler is configured as an application component in [[\yii\base\Application]] by default.
 * You can access that instance via `Yew::$app->errorHandler`.
 *
 * For more details and usage information on ErrorHandler, see the [guide article on handling errors](guide:runtime-handling-errors).
 *
 * @author Qiang Xue <qiang.xue@gmail.com>
 * @author Alexander Makarov <sam@rmcreative.ru>
 * @author Carsten Brandt <mail@cebe.cc>
 * @since 2.0
 */
abstract class ErrorHandler extends Component
{
    /**
     * @event Event an event that is triggered when the handler is called by shutdown function via [[handleFatalError()]].
     * @since 2.0.46
     */
    const EVENT_SHUTDOWN = 'shutdown';

    /**
     * @var bool whether to discard any existing page output before error display. Defaults to true.
     */
    public bool $discardExistingOutput = true;

    /**
     * @var int the size of the reserved memory. A portion of memory is pre-allocated so that
     * when an out-of-memory issue occurs, the error handler is able to handle the error with
     * the help of this reserved memory. If you set this value to be 0, no memory will be reserved.
     * Defaults to 256KB.
     */
    public int $memoryReserveSize = 262144;

    /**
     * @var \Exception|null the exception that is being handled currently.
     */
    public $exception;

    /**
     * @var bool if true - `handleException()` will finish script with `ExitCode::OK`.
     * false - `ExitCode::UNSPECIFIED_ERROR`.
     * @since 2.0.36
     */
    public ?bool $silentExitOnException = null;

    /**
     * @var string Used to reserve memory for fatal error handler.
     */
    private ?string $_memoryReserve;

    /**
     * @var \Exception from HHVM error that stores backtrace
     */
    private \Exception $_hhvmException;

    /**
     * @var bool whether this instance has been registered using `register()`
     */
    private bool $_registered = false;


    protected bool $isDebug = true;

    public function init()
    {
        $this->isDebug = Yew::$app->getConfig()->get('yew.debug');

        $this->silentExitOnException = $this->silentExitOnException !== null ? $this->silentExitOnException : $this->isDebug;
        parent::init();
    }

    /**
     * Register this error handler.
     * @since 2.0.32 this will not do anything if the error handler was already registered
     */
    public function register()
    {
        if (!$this->_registered) {
            ini_set('display_errors', false);
            set_exception_handler([$this, 'handleException']);

            set_error_handler([$this, 'handleError']);

            if ($this->memoryReserveSize > 0) {
                $this->_memoryReserve = str_repeat('x', $this->memoryReserveSize);
            }
            register_shutdown_function([$this, 'handleFatalError']);
            $this->_registered = true;
        }
    }

    /**
     * Unregisters this error handler by restoring the PHP error and exception handlers.
     * @since 2.0.32 this will not do anything if the error handler was not registered
     */
    public function unregister()
    {
        if ($this->_registered) {
            restore_error_handler();
            restore_exception_handler();
            $this->_registered = false;
        }
    }

    /**
     * Handles uncaught PHP exceptions.
     *
     * This method is implemented as a PHP exception handler.
     *
     * @param \Exception $exception the exception that is not caught
     * @throws \Exception
     */
    public function handleException($exception)
    {
        var_dump([
            'mark' => 'handleException',
        ]);

        if ($exception instanceof ExitException) {
            return;
        }

        $this->exception = $exception;

        // disable error capturing to avoid recursive errors while handling exceptions
        $this->unregister();

        // set preventive HTTP status code to 500 in case error handling somehow fails and headers are sent
        // HTTP exceptions will override this value in renderException()
        if (PHP_SAPI !== 'cli') {
            http_response_code(500);
        }

        try {
            $this->logException($exception);
            if ($this->discardExistingOutput) {
                $this->clearOutput();
            }
            $this->renderException($exception);
            if (!$this->silentExitOnException) {
                Yew::getLogger()->flush(true);
                return;
            }
        } catch (\Exception $e) {
            // an other exception could be thrown while displaying the exception
            $this->handleFallbackExceptionMessage($e, $exception);
        } catch (\Throwable $e) {
            // additional check for \Throwable introduced in PHP 7
            $this->handleFallbackExceptionMessage($e, $exception);
        }

        $this->exception = null;
    }

    /**
     * Handles exception thrown during exception processing in [[handleException()]].
     * @param \Exception|\Throwable $exception Exception that was thrown during main exception processing.
     * @param \Exception $previousException Main exception processed in [[handleException()]].
     * @throws \Exception
     * @since 2.0.11
     */
    protected function handleFallbackExceptionMessage($exception, $previousException)
    {
        $msg = "An Error occurred while handling another error:\n";
        $msg .= (string)$exception;
        $msg .= "\nPrevious exception:\n";
        $msg .= (string)$previousException;

        if ($this->isDebug) {
            if (PHP_SAPI === 'cli') {
                echo $msg . "\n";
            } else {
                echo '<pre>' . htmlspecialchars($msg, ENT_QUOTES, Yew::$app->charset) . '</pre>';
            }
        } else {
            echo 'An internal server error occurred.';
        }
        $msg .= "\n\$_SERVER = " . VarDumper::export($_SERVER);
        error_log($msg);
    }

    /**
     * Handles PHP execution errors such as warnings and notices.
     *
     * This method is used as a PHP error handler. It will simply raise an [[ErrorException]].
     *
     * @param int $code the level of the error raised.
     * @param string $message the error message.
     * @param string $file the filename that the error was raised in.
     * @param int $line the line number the error was raised at.
     * @return bool whether the normal error handler continues.
     *
     * @throws ErrorException
     * @throws \Exception
     */
    public function handleError(int $code, string $message, string $file, int $line): bool
    {
        var_dump([
            error_reporting(),
            $code,
            error_reporting() & $code
        ]);
        if (error_reporting() & $code) {
            // load ErrorException manually here because autoloading them will not work
            // when error occurs while autoloading a class
            if (!class_exists('Yew\Framework\Exception\ErrorException', false)) {
                require_once dirname(__DIR__) . '/Exception/ErrorException.php';
            }
            $exception = new ErrorException($message, $code, $code, $file, $line);

            throw $exception;
        }

        return false;
    }

    /**
     * Handles fatal PHP errors.
     */
    public function handleFatalError()
    {
        $this->_memoryReserve = null;

        if (!empty($this->_workingDirectory)) {
            // fix working directory for some Web servers e.g. Apache
            chdir($this->_workingDirectory);
            // flush memory
            $this->_workingDirectory = null;
        }

        $error = error_get_last();
        if ($error === null) {
            return;
        }

        // load ErrorException manually here because autoloading them will not work
        // when error occurs while autoloading a class
        if (!class_exists('Yew\Framework\Exception\ErrorException', false)) {
            require_once dirname(__DIR__) . '/Exception/ErrorException.php';
        }

        if (!ErrorException::isFatalError($error)) {
            return;
        }

        $this->exception = new ErrorException(
            $error['message'],
            $error['type'],
            $error['type'],
            $error['file'],
            $error['line']
        );

        unset($error);

        $this->logException($this->exception);

        if ($this->discardExistingOutput) {
            $this->clearOutput();
        }
        $this->renderException($this->exception);

        // need to explicitly flush logs because exit() next will terminate the app immediately
        Yew::getLogger()->flush(true);
        if (defined('HHVM_VERSION')) {
            flush();
        }

        $this->trigger(static::EVENT_SHUTDOWN);

        // ensure it is called after user-defined shutdown functions
        register_shutdown_function(function () {
            exit(1);
        });
    }

    /**
     * Renders the exception.
     * @param \Exception $exception the exception to be rendered.
     */
    abstract protected function renderException(\Exception $exception);

    /**
     * Logs the given exception.
     * @param \Exception $exception the exception to be logged
     * @since 2.0.3 this method is now public.
     */
    public function logException($exception)
    {
        $category = get_class($exception);
        if ($exception instanceof HttpException) {
            $category = 'Yew\Framework\Web\HttpException:' . $exception->statusCode;
        } elseif ($exception instanceof \ErrorException) {
            $category .= ':' . $exception->getSeverity();
        }
        Yew::error($exception, $category);
    }

    /**
     * Removes all output echoed before calling this method.
     */
    public function clearOutput()
    {
        // the following manual level counting is to deal with zlib.output_compression set to On
        for ($level = ob_get_level(); $level > 0; --$level) {
            if (!@ob_end_clean()) {
                ob_clean();
            }
        }
    }

    /**
     * Converts an exception into a PHP error.
     *
     * This method can be used to convert exceptions inside of methods like `__toString()`
     * to PHP errors because exceptions cannot be thrown inside of them.
     * @param \Exception $exception the exception to convert to a PHP error.
     */
    public function convertExceptionToError(\Exception $exception)
    {
        trigger_error($this->convertExceptionToString($exception), E_USER_ERROR);
    }

    /**
     * Converts an exception into a simple string.
     * @param \Exception|\Error $exception the exception being converted
     * @return string the string representation of the exception.
     */
    public function convertExceptionToString($exception): string
    {
        if ($exception instanceof UserException) {
            return "{$exception->getName()}: {$exception->getMessage()}";
        }

        if ($this->isDebug) {
            return $this->convertExceptionToVerboseString($exception);
        }

        return 'An internal server error occurred.';
    }

    /**
     * Converts an exception into a string that has verbose information about the exception and its trace.
     * @param \Exception|\Error $exception the exception being converted
     * @return string the string representation of the exception.
     *
     * @since 2.0.14
     */
    public function convertExceptionToVerboseString($exception): string
    {
        if ($exception instanceof Exception) {
            $message = "Exception ({$exception->getName()})";
        } elseif ($exception instanceof ErrorException) {
            $message = (string)$exception->getName();
        } else {
            $message = 'Exception';
        }
        $message .= " '" . get_class($exception) . "' with message '{$exception->getMessage()}' \n\nin "
            . $exception->getFile() . ':' . $exception->getLine() . "\n\n"
            . "Stack trace:\n" . $exception->getTraceAsString();

        return $message;
    }
}
