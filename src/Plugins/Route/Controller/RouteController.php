<?php
/**
 * Yew framework
 * @author bearlord <565364226@qq.com>
 */

namespace Yew\Plugins\Route\Controller;

use DI\Annotation\Inject;
use Yew\Core\Exception\ParamException;
use Yew\Core\Log\LoggerInterface;
use Yew\Core\Server\Beans\Request;
use Yew\Core\Server\Beans\Response;
use Yew\Plugins\Route\Annotation\ResponseBody;
use Yew\Plugins\Route\MethodNotAllowedException;
use Yew\Plugins\Route\RouteException;
use Yew\Plugins\Pack\ClientData;
use Yew\Coroutine\Server\Server;
use Yew\Framework\Base\Controller;
use Yew\Framework\Exception\InvalidRouteException;

abstract class RouteController extends Controller implements IController
{
    /**
     * @event ActionEvent an event raised right before executing a controller action.
     * You may set [[ActionEvent::isValid]] to be false to cancel the action execution.
     */
    const EVENT_BEFORE_ACTION = 'beforeAction';
    /**
     * @event ActionEvent an event raised right after executing a controller action.
     */
    const EVENT_AFTER_ACTION = 'afterAction';

    /**
     * @Inject()
     * @var Request
     */
    protected $request;

    /**
     * @Inject()
     * @var Response
     */
    protected $response;

    /**
     * @Inject()
     * @var ClientData
     */
    protected $clientData;

    /**
     * @Inject()
     * @var LoggerInterface
     */
    protected LoggerInterface $log;

    /**
     * @inheritDoc
     * @param string|null $controllerName
     * @param string|null $methodName
     * @param array|null $params
     * @return mixed
     * @throws \Throwable
     */
    public function handle(?string $controllerName = null, ?string $methodName = null, ?array $params = null)
    {
        if (!is_callable([$this, $methodName]) || $methodName == null) {
            $callMethodName = 'defaultMethod';
        } else {
            $callMethodName = $methodName;
        }
        try {
            $action = $this->createAction($callMethodName);
            if ($action === null) {
                throw new InvalidRouteException('Unable to create Action: ' . $controllerName . '::' . $methodName);
            }

            $result = null;
            if ($this->beforeAction($action)) {
                // run the action
                $result = $action->runWithParams($params);
                $result = $this->afterAction($action, $result);
            }
            return $result;
        } catch (\Throwable $exception) {
            Server::$instance->getLog()->error($exception);

            /** @var ClientData $clientData */
            $clientData = getContextValueByClassName(ClientData::class);
            $annotations = $clientData->getAnnotations();
            foreach ($annotations as $annotation) {
                if ($annotation instanceof ResponseBody) {
                    return [
                        "code" => $exception->getCode(),
                        "message" => $exception->getMessage(),
                        "file" => $exception->getFile(),
                        "line" => $exception->getLine(),
                        "trace" => $exception->getTrace()
                    ];
                }
            }

            setContextValue("lastException", $exception);
            return $this->onExceptionHandle($exception);
        }
    }

    /**
     * Called on every request
     *
     * @param string|null $controllerName
     * @param string|null $methodName
     * @return void
     */
    public function initialization(?string $controllerName, ?string $methodName)
    {

    }

    /**
     *
     * @param string|null $methodName
     * @return mixed
     */
    abstract protected function defaultMethod(?string $methodName);

    /**
     * @inheritDoc
     * @param $exception
     * @return mixed
     * @throws \Throwable
     */
    public function onExceptionHandle(\Throwable $exception)
    {
        if ($this->clientData->getResponse() != null) {
            $this->response->withStatus(404);
            $this->response->withHeader("Content-Type", "text/html;charset=UTF-8");

            if ($exception instanceof RouteException) {
                $msg = '404 Not found / ' . $exception->getMessage();
            } elseif ($exception instanceof ParamException) {
                $this->response->withStatus(400);
                $msg = '400 Bad request / ' . $exception->getMessage();
            } else if ($exception instanceof MethodNotAllowedException) {
                $this->response->withStatus(405);
                $msg = '405 method not allowed';
            } else {
                $this->response->withStatus(500);
                $msg = '500 internal server error';
            }
            return $msg;
        } else {
            return $exception->getMessage();
        }
    }

    /**
     * Refreshes the current page.
     * This method is a shortcut to [[Response::refresh()]].
     *
     * You can use it in an action by returning the [[Response]] directly:
     *
     * ```php
     * // stop executing this action and refresh the current page
     * return $this->refresh();
     * ```
     *
     * @param string|null $anchor the anchor that should be appended to the redirection URL.
     * Defaults to empty. Make sure the anchor starts with '#' if you want to specify it.
     * @return Response the response object itself
     */
    public function refresh(?string $anchor = ''): Response
    {
        return $this->response->redirect($this->request->getUri() . $anchor);
    }
}
