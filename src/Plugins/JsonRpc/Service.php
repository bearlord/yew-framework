<?php
/**
 * Yew framework
 * @author bearload <565364226@qq.com>
 */

namespace Yew\Plugins\JsonRpc;

use Yew\Framework\Base\Action;
use Yew\Framework\Base\Component;
use Yew\Framework\Exception\Exception;
use Yew\Plugins\JsonRpc\InlineAction;
use Yew\Yew;

class Service extends Component
{
    /**
     * Binds the parameters to the action.
     * This method is invoked by [[Action]] when it begins to run with the given parameters.
     * @param Action $action the action to be bound with parameters.
     * @param array $params the parameters to be bound to the action.
     * @return array the valid parameters that the action can run with.
     */
    public function bindActionParams(Action $action, array $params)
    {
        if ($action instanceof InlineAction) {
            $method = new \ReflectionMethod($this, $action->actionMethod);
        } else {
            $method = new \ReflectionMethod($action, "run");
        }

        $args = [];
        $missing = [];
        $actionParams = [];
        $requestedParams = [];

        foreach ($method->getParameters() as $param) {
            $name = $param->getName();
            $key = null;
            if (array_key_exists($i, $params)) {
                $key = $i;
            } elseif (array_key_exists($name, $params)) {
                $key = $name;
            }

            if ($key !== null) {
                $isValid = true;
                $type = $param->getType();
                if ($type instanceof \ReflectionNamedType) {
                    [$result, $isValid] = $this->filterSingleTypeActionParam($params[$key], $type);
                    $params[$key] = $result;
                } elseif ($type instanceof \ReflectionUnionType) {
                    [$result, $isValid] = $this->filterUnionTypeActionParam($params[$key], $type);
                    $params[$key] = $result;
                }

                if (!$isValid) {
                    throw new Exception(Yew::t('yew', 'Invalid data received for parameter "{param}".', ['param' => $name]));
                }

                $args[] = $actionParams[$key] = $params[$key];
                unset($params[$key]);
            } elseif (
                PHP_VERSION_ID >= 70100
                && ($type = $param->getType()) !== null
                && $type instanceof \ReflectionNamedType
                && !$type->isBuiltin()
            ) {
                try {
                    $this->bindInjectedParams($type, $name, $args, $requestedParams);
                } catch (Exception $e) {
                    throw new Exception($e->getMessage());
                }
            } elseif ($param->isDefaultValueAvailable()) {
                $args[] = $actionParams[$name] = $param->getDefaultValue();
            } else {
                $missing[] = $name;
            }
        }

        if (!empty($missing)) {
            throw new Exception(Yew::t("yii", "Missing required arguments: {params}", ["params" => implode(", ", $missing)]));
        }

        return $args;
    }

    /**
     * Fills parameters based on types and names in action method signature.
     * @param \ReflectionType $type The reflected type of the action parameter.
     * @param string $name The name of the parameter.
     * @param array &$args The array of arguments for the action, this function may append items to it.
     * @param array &$requestedParams The array with requested params, this function may write specific keys to it.
     * @throws \Yew\Framework\Exception\Exception
     * @throws \Yew\Framework\Exception\InvalidConfigException Thrown when there is an error in the DI configuration.
     * @throws \Yew\Framework\Di\NotInstantiableException
     * @throws \ReflectionException
     * @since 2.0.36
     */
    final protected function bindInjectedParams(\ReflectionType $type, string $name, array &$args, array &$requestedParams)
    {
        // Since it is not a builtin type it must be DI injection.
        $typeName = $type->getName();
        if (Yew::$container->has($typeName) && ($service = Yew::$container->get($typeName)) instanceof $typeName) {
            $args[] = $service;
            $requestedParams[$name] = "Container DI: $typeName \$$name";
        } elseif ($type->allowsNull()) {
            $args[] = null;
            $requestedParams[$name] = "Unavailable service: $name";
        } else {
            throw new Exception('Could not load required service: ' . $name);
        }
    }
}