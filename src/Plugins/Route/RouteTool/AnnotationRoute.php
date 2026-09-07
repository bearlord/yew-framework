<?php
/**
 * Yew framework
 * @author bearlord <565364226@qq.com>
 */

namespace Yew\Plugins\Route\RouteTool;

use Yew\Core\Exception\ParamException;
use Yew\Core\Plugins\Logger\GetLogger;
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

class AnnotationRoute implements IRoute
{
	use GetLogger;

	/**
	 * @var ClientData
	 */
	private $clientData;

    /**
     * @inheritDoc
     * @return string
     */
    public function getControllerName(): ?string
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
    public function getMethodName(): ?string
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
    public function getPath(): ?string
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
    public function getParams(): ?array
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

    /**
     * Dispatcher not found
     * @return void
     */
    protected function dispatcherNotFound()
    {
        $_path = $this->clientData->getPath() ?? "";
        $message = sprintf("Path %s not found", $_path);

        $this->warn($message);

        if (!empty($this->clientData->getRequest())) {
            $contentType = $this->clientData->getRequest()->getContentType();
            if (strpos($contentType, "application/json") !== false) {
                $this->clientData->getResponse()->withHeader("Content-Type", $contentType);
                $exceptionJson = Json::encode([
                    "code" => 400,
                    "data" => null,
                    "message" => $message
                ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_FORCE_OBJECT);
                $this->clientData->getResponse()->withContent($exceptionJson)->end();
            }
        }
    }

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
	public function handleClientData(ClientData $clientData, RoutePortConfig $RoutePortConfig): bool
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
				$this->dispatcherNotFound();

				break;

			case Dispatcher::METHOD_NOT_ALLOWED:
				if (!empty($this->clientData->getRequest()) && $this->clientData->getRequest()->getMethod() == "OPTIONS") {
					$methods = [];
					foreach ($routeInfo[1] as $value) {
						[, $method] = explode(":", $value);
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

				// Read the request body once: PSR-7 streams are not rewindable,
				// so a second getContents() would return an empty string.
				$rawBody = $request !== null ? $request->getBody()->getContents() : '';

				// ResponeJsonRpc is a response-side annotation, handled separately
				// so resolveAnnotationParams stays free of side effects.
				$this->applyResponseAnnotations($annotations);

				$params = $this->resolveAnnotationParams($annotations, $request, $rawBody, $vars);
				$realParams = $this->buildRealParams($methodReflection, $params);

				if (!empty($realParams)) {
					$this->clientData->setParams($realParams);
				}
				break;
		}
		return true;
	}

	/**
	 * Resolve request parameters from request-side annotations.
	 *
	 * Handles PathVariable, RequestParam, RequestFormData, RequestRawJson,
	 * RequestBody, RequestRaw and RequestRawXml. Response-side annotations
	 * (e.g. ResponeJsonRpc) are NOT processed here — see applyResponseAnnotations.
	 *
	 * @param array $annotations Method + interface annotations
	 * @param mixed $request PSR-7 request or null
	 * @param string $rawBody Request body read once (non-rewindable stream)
	 * @param array $vars Path variables matched by the router
	 * @return array Resolved parameters keyed by param name
	 * @throws ParamException
	 * @throws RouteException
	 */
	protected function resolveAnnotationParams(array $annotations, $request, string $rawBody, array $vars): array
	{
		$params = [];

		foreach ($annotations as $annotation) {
			switch (true) {
				case ($annotation instanceof PathVariable):
					$result = $vars[$annotation->value] ?? null;
					if ($annotation->required && $result == null) {
						throw new RouteException("path {$annotation->value} not found");
					}
					$params[$annotation->param ?? $annotation->value] = $result;
					break;

				case ($annotation instanceof RequestParam):
					$result = $request !== null ? $request->query($annotation->value) : null;
					if ($annotation->required && $result == null) {
						throw new ParamException("require params $annotation->value");
					}
					$params[$annotation->param ?? $annotation->value] = $result;
					break;

				case ($annotation instanceof RequestFormData):
					$result = $request !== null ? $request->post($annotation->value) : null;
					if ($annotation->required && $result == null) {
						throw new ParamException("require params $annotation->value");
					}
					$params[$annotation->param ?? $annotation->value] = $result;
					break;

				case ($annotation instanceof RequestRawJson):
				case ($annotation instanceof RequestBody):
					if (!$json = json_decode($rawBody, true)) {
						$this->warning("RequestRawJson error, raw:" . $rawBody);
						throw new RouteException("RawJson Format error");
					}
					if (!empty($annotation->value)) {
						$params[$annotation->value] = $json;
					} else {
						// Merge instead of overwriting, so path/query params
						// resolved earlier are not lost when both coexist.
						$params = array_merge($params, $json);
					}
					break;

				case ($annotation instanceof RequestRaw):
					$params[$annotation->value] = $rawBody;
					break;

				case ($annotation instanceof RequestRawXml):
					if (!$xml = simplexml_load_string($rawBody, "SimpleXMLElement", LIBXML_NOCDATA | LIBXML_NOBLANKS)) {
						$this->warning("RequestRawXml error, raw:" . $rawBody);
						throw new RouteException("RawXml Format error");
					}
					$params[$annotation->value] = json_decode(json_encode($xml), true);
					break;
			}
		}

		return $params;
	}

	/**
	 * Apply response-side annotations (side effects only, no params).
	 *
	 * @param array $annotations
	 * @return void
	 */
	protected function applyResponseAnnotations(array $annotations): void
	{
		foreach ($annotations as $annotation) {
			if ($annotation instanceof ResponeJsonRpc) {
				$this->clientData->getResponse()->withHeader("Content-Type", $annotation->value);
			}
		}
	}

	/**
	 * Build the final ordered parameter list from resolved params
	 * and the method's reflection parameters.
	 *
	 * @param \ReflectionMethod $methodReflection
	 * @param array $params
	 * @return array
	 */
	protected function buildRealParams(\ReflectionMethod $methodReflection, array $params): array
	{
		$realParams = [];
		foreach ($methodReflection->getParameters() as $parameter) {
			$paramClass = null;
			$paramType = $parameter->getType();
			if ($paramType instanceof \ReflectionNamedType && !$paramType->isBuiltin()) {
				$paramClass = $paramType->getName();
			}
			if ($paramClass != null) {
				$values = $params[$parameter->name];
				if ($values != null) {
					$values = ValidatedFilter::valid($paramClass, $values);
					$instance = new $paramClass();
					foreach ($instance as $key => $_) {
						$instance->$key = $values[$key] ?? null;
					}
					$realParams[$parameter->getPosition()] = $instance;
				} else {
					$realParams[$parameter->getPosition()] = null;
				}
			} else {
				$realParams[$parameter->getPosition()] = $params[$parameter->name] ?? "";
			}
		}
		return $realParams;
	}


}
