<?php
/**
 * @link http://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license http://www.yiiframework.com/license/
 */

namespace Yew\Framework\Base;

use Yew\Yew;

/**
 * InlineAction represents an action that is defined as a controller method.
 *
 * The name of the controller method is available via [[actionMethod]] which
 * is set by the [[controller]] who creates this action.
 *
 * For more details and usage information on InlineAction, see the [guide article on actions](guide:structure-controllers).
 *
 * @author Qiang Xue <qiang.xue@gmail.com>
 * @since 2.0
 */
class InlineAction extends Action
{
    /**
     * @var string the controller method that this inline action is associated with
     */
    public string $actionMethod;


    /**
     * @param string $id the ID of this action
     * @param Controller $controller the controller that owns this action
     * @param string $actionMethod the controller method that this inline action is associated with
     * @param array|null $config name-value pairs that will be used to initialize the object properties
     */
    public function __construct(string $id, Controller $controller, string $actionMethod, ?array $config = [])
    {
        $this->actionMethod = $actionMethod;
        parent::__construct($id, $controller, $config);
    }

    /**
     * Runs this action with the specified parameters.
     * This method is mainly invoked by the controller.
     * @param array|null $params action parameters
     * @return mixed the result of the action
     * @throws \Yew\Framework\Exception\Exception
     * @throws \ReflectionException
     */
    public function runWithParams(?array $params = null)
    {
        $args = $this->controller->bindActionParams($this, $params);

        Yew::debug('Running action: ' . get_class($this->controller) . '::' . $this->actionMethod . '()', __METHOD__);

        return call_user_func_array([$this->controller, $this->actionMethod], $args);
    }
}
