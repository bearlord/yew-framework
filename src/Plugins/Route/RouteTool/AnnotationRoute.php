<?php
/**
 * Yew framework
 * @author bearlord <565364226@qq.com>
 */

namespace Yew\Plugins\Route\RouteTool;

use Yew\Core\Exception\ParamException;
use Yew\Core\Plugins\Logger\GetLogger;
use Yew\Coroutine\Server\Server;
use Yew\Plugins\Route\Annotation\ModelAttribute;
use Yew\Plugins\Route\Annotation\PathVariable;
use Yew\Plugins\Route\Annotation\RequestBody;
use Yew\Plugins\Route\Annotation\RequestFormData;
use Yew\Plugins\Route\Annotation\RequestParam;
use Yew\Plugins\Route\Annotation\RequestRaw;
use Yew\Plugins\Route\Annotation\RequestRawJson;
use Yew\Plugins\Route\Annotation\RequestRawXml;
use Yew\Plugins\Route\Annotation\ResponseBody;
use Yew\Plugins\Route\RoutePortConfig;
use Yew\Plugins\Route\RoutePlugin;
use Yew\Plugins\Route\MethodNotAllowedException;
use Yew\Plugins\Route\RouteException;
use Yew\Plugins\JsonRpc\Annotation\ResponeJsonRpc;
use Yew\Plugins\Pack\ClientData;
use Yew\Plugins\Validate\Annotation\ValidatedFilter;
use Yew\Utils\ArrayToXml;
use Yew\Framework\Helpers\Json;
use Yew\Yew;
use Yew\Nikic\FastRoute\Dispatcher;

/**
 * Class AnnotationRoute
 * @package Yew\Plugins\Route\RouteTool
 */
class AnnotationRoute implements IRoute
{
    use GetLogger;

    /**
     * @var ClientData
     */
    private $clientData;

    /**
     * @inheritDoc
     * @param ClientData $clientData
     * @param RoutePortConfig $RoutePortConfig
     * @return bool
     * @throws MethodNotAllowedException
     * @throws ParamException
     * @throws RouteException
     * @throws \Yew\Plugins\Validate\ValidationException
     * @throws \ReflectionException
     */
    public function handleClientData($clientData, $RoutePortConfig): bool
    {
        $this->clientData = $clientData;
        //Port
        $port = $this->clientData->getClientInfo()->getServerPort();
        //Request method
        $requestMethod = strtoupper($this->clientData->getRequestMethod());
        //Route info
        $routeInfo = RoutePlugin::$instance->getDispatcher()->dispatch(sprintf("%s:%s", $port, $requestMethod), $this->clientData->getPath());

        $request = $this->clientData->getRequest();

        switch ($routeInfo[0]) {
            case Dispatcher::NOT_FOUND:
                $message = "Path not found";

                $debug = Server::$instance->getConfigContext()->get("yew.server.debug");
                if ($debug) {
                    throw new RouteException($message);
                    break;
                }

                $contentType = $this->clientData->getRequest()->getContentType();
                if (strpos($contentType, 'application/json') !== false) {
                    $this->clientData->getResponse()->withHeader("Content-Type", $contentType);
                    $exceptionJson = Json::encode([
                        'code' => 400,
                        'data' => [],
                        'message' => $message
                    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_FORCE_OBJECT);
                    $this->clientData->getResponse()->withContent($exceptionJson)->end();
                }
                break;

            case Dispatcher::METHOD_NOT_ALLOWED:
                if ($this->clientData->getRequest()->getMethod() == "OPTIONS") {
                    $methods = [];
                    foreach ($routeInfo[1] as $value) {
                        list($port, $method) = explode(":", $value);
                        $methods[] = $method;
                    }
                    $this->clientData->getResponse()->withHeader("Access-Control-Allow-Methods", implode(",", $methods));
                    $this->clientData->getResponse()->end();
                    return false;
                } else {
                    throw new MethodNotAllowedException("Method not allowed");
                }
                break;

            case Dispatcher::FOUND:
                $handler = $routeInfo[1];
                $vars = $routeInfo[2];
                $this->clientData->setControllerName($handler[0]->name);
                $this->clientData->setMethodName($handler[1]->name);
                $params = [];
                $methodReflection = $handler[1]->getReflectionMethod();
                $annotations = RoutePlugin::$instance->getScanClass()->getMethodAndInterfaceAnnotations($methodReflection);
                $this->clientData->setAnnotations($annotations);

                foreach ($annotations as $annotation) {
                    switch (true) {
                        case ($annotation instanceof PathVariable):
                            $result = $vars[$annotation->value] ?? null;
                            if ($annotation->required) {
                                if ($result == null) {
                                    throw new RouteException("path {$annotation->value} not found");
                                }
                            }
                            $params[$annotation->param ?? $annotation->value] = $result;
                            break;

                        case ($annotation instanceof RequestParam):
                            if ($request == null) {
                                break;
                            }
                            $result = $request->query($annotation->value);
                            if ($annotation->required && $result == null) {
                                throw new ParamException("require params $annotation->value");
                            }
                            $params[$annotation->param ?? $annotation->value] = $result;
                            break;

                        case ($annotation instanceof RequestFormData):
                            if ($request == null) {
                                break;
                            }
                            $result = $request->post($annotation->value);
                            if ($annotation->required && $result == null) {
                                throw new ParamException("require params $annotation->value");
                            }
                            $params[$annotation->param ?? $annotation->value] = $result;
                            break;

                        case ($annotation instanceof RequestRawJson):
                        case ($annotation instanceof RequestBody):
                            if ($request == null) {
                                break;
                            }
                            if (!$json = json_decode($request->getBody()->getContents(), true)) {
                                $this->warning('RequestRawJson error, raw:' . $request->getBody()->getContents());
                                throw new RouteException('RawJson Format error');
                            }
                            if (!empty($annotation->value)) {
                                $params[$annotation->value] = $json;
                            } else {
                                $params = $json;
                            }
                            break;

                        case ($annotation instanceof RequestRaw):
                            if ($request == null) {
                                break;
                            }
                            $raw = $request->getBody()->getContents();
                            $params[$annotation->value] = $raw;
                            break;

                        case ($annotation instanceof RequestRawXml):
                            if ($request == null) {
                                break;
                            }
                            $raw = $request->getBody()->getContents();
                            if (!$xml = simplexml_load_string($raw, 'SimpleXMLElement', LIBXML_NOCDATA | LIBXML_NOBLANKS)) {
                                $this->warning('RequestRawXml error, raw:' . $request->getBody()->getContents());
                                throw new RouteException('RawXml Format error');
                            }
                            $params[$annotation->value] = json_decode(json_encode($xml), true);
                            break;

                        case ($annotation instanceof ResponeJsonRpc):
                            $clientData->getResponse()->withHeader("Content-Type", $annotation->value);
                            break;

                    }
                }
                $realParams = [];
                if ($methodReflection instanceof \ReflectionMethod) {
                    foreach ($methodReflection->getParameters() as $parameter) {
                        if ($parameter->getClass() != null) {
                            $values = $params[$parameter->name];
                            if ($values != null) {
                                $values = ValidatedFilter::valid($parameter->getClass(), $values);
                                $instance = $parameter->getClass()->newInstance();
                                foreach ($instance as $key => $value) {
                                    $instance->$key = $values[$key] ?? null;
                                }
                                $realParams[$parameter->getPosition()] = $instance;
                            } else {
                                $realParams[$parameter->getPosition()] = null;
                            }
                        } else {
                            $realParams[$parameter->getPosition()] = $params[$parameter->name] ?? '';
                        }
                    }
                }

                if (!empty($realParams)) {
                    $this->clientData->setParams($realParams);
                }
                break;
        }
        return true;
    }

    /**
     * @inheritDoc
     * @return string
     */
    public function getControllerName()
    {
        if ($this->clientData == null) {
            return null;
        }
        return $this->clientData->getControllerName();
    }

    /**
     * @inheritDoc
     * @return string
     */
    public function getMethodName()
    {
        if ($this->clientData == null) {
            return null;
        }
        return $this->clientData->getMethodName();
    }

    /**
     * @inheritDoc
     * @return string|null
     */
    public function getPath()
    {
        if ($this->clientData == null) {
            return null;
        }
        return $this->clientData->getPath();
    }

    /**
     * @inheritDoc
     * @return array|null
     */
    public function getParams()
    {
        if ($this->clientData == null) {
            return null;
        }
        return $this->clientData->getParams();
    }

    /**
     * Get client data
     *
     * @return ClientData
     */
    public function getClientData(): ?ClientData
    {
        return $this->clientData;
    }
}
