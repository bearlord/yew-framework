<?php
/**
 * @link http://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license http://www.yiiframework.com/license/
 */

namespace Yew\Framework\Db;

/**
 * Expression represents a DB expression that does not need escaping or quoting.
 *
 * When an Expression object is embedded within a SQL statement or fragment,
 * it will be replaced with the [[expression]] property value without any
 * DB escaping or quoting. For example,
 *
 * ```php
 * $expression = new Expression('NOW()');
 * $now = (new \Yew\Framework\Db\Query)->select($expression)->scalar();  // SELECT NOW();
 * echo $now; // prints the current date
 * ```
 *
 * Expression objects are mainly created for passing raw SQL expressions to methods of
 * [[Query]], [[ActiveQuery]], and related classes.
 *
 * An expression can also be bound with parameters specified via [[params]].
 *
 * @author Qiang Xue <qiang.xue@gmail.com>
 * @since 2.0
 */
class Expression extends \Yew\Framework\Base\BaseObject implements ExpressionInterface
{
    /**
     * @var string the DB expression
     */
    public string $expression;
    /**
     * @var array list of parameters that should be bound for this expression.
     * The keys are placeholders appearing in [[expression]] and the values
     * are the corresponding parameter values.
     */
    public array $params = [];


    /**
     * Constructor.
     * @param string $expression the DB expression
     * @param array|null $params parameters
     * @param array|null $config name-value pairs that will be used to initialize the object properties
     */
    public function __construct($expression, ?array $params = [], ?array $config = [])
    {
        $this->expression = $expression;
        $this->params = $params;
        parent::__construct($config);
    }

    /**
     * String magic method.
     * @return string the DB expression.
     */
    public function __toString()
    {
        return $this->expression;
    }
}
