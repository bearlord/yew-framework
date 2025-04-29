<?php
/**
 * @link http://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license http://www.yiiframework.com/license/
 */

namespace Yew\Framework\Db\Conditions;

/**
 * Condition based on column-value pairs.
 *
 * @author Dmytro Naumenko <d.naumenko.a@gmail.com>
 * @since 2.0.14
 */
class HashCondition implements ConditionInterface
{
    /**
     * @var array|null the condition specification.
     */
    private ?array $hash;


    /**
     * HashCondition constructor.
     *
     * @param array|null $hash
     */
    public function __construct(?array $hash = null)
    {
        $this->hash = $hash;
    }

    /**
     * @return array|null
     */
    public function getHash(): ?array
    {
        return $this->hash;
    }

    /**
     * {@inheritdoc}
     */
    public static function fromArrayDefinition(string $operator, array $operands): ConditionInterface
    {
        return new static($operands);
    }
}
