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
            $method = new \ReflectionMethod($action, 'run');
        }

        $args = [];
        $missing = [];
        $actionParams = [];
        $requestedParams = [];

        foreach ($method->getParameters() as $i => $param) {
            $name = $param->getName();
            $key = null;
            if (array_key_exists($i, $params)) {
                $key = $i;
            } elseif (array_key_exists($name, $params)) {
                $key = $name;
            }

            if ($key !== null) {
                if ($param->isArray()) {
                    $params[$key] = $params[$key] === '' ? [] : preg_split('/\s*,\s*/', $params[$key]);
                }
                $args[] = $actionParams[$key] = $params[$key];
                unset($params[$key]);
            } elseif (PHP_VERSION_ID >= 70100 && ($type = $param->getType()) !== null && !$type->isBuiltin()) {
                try {
                    $this->bindInjectedParams($type, $name, $args, $requestedParams);
                } catch (Exception $e) {
                    throw new Exception($e->getMessage());
                }
            } elseif ($param->isDefaultValueAvailable()) {
                $args[] = $actionParams[$i] = $param->getDefaultValue();
            } else {
                $missing[] = $name;
            }
        }

        if (!empty($missing)) {
            throw new Exception(Yii::t('yii', 'Missing required arguments: {params}', ['params' => implode(', ', $missing)]));
        }

        return $args;
    }
}