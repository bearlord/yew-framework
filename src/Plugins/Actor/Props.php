<?php
/**
 * Yew framework
 * @author bearlord <565364226@qq.com>
 */

namespace Yew\Plugins\Actor;

/**
 * Immutable configuration object describing how to instantiate an actor.
 *
 * Mirrors Akka's `akka.actor.Props`: it bundles the actor class together with
 * its init data and deployment hints (name, parent, routing key, …) behind a
 * single value that can be passed to {@see ActorSystem::actorOf()}.
 *
 * Instances are immutable; every `with*()` method returns a new Props, so a
 * Props can be safely shared and partially specialised, exactly like Akka.
 */
class Props
{
    /**
     * @var string Actor class name
     */
    private string $actionClass;

    /**
     * @var mixed|null Init data passed to the actor on creation
     */
    private $data;

    /**
     * @var string|null Explicit actor name (null -> auto-generated)
     */
    private ?string $name;

    /**
     * @var string|null Supervision parent (child shares its process)
     */
    private ?string $parentName;

    /**
     * @var string|null Consistent-hash routing key
     */
    private ?string $routingKey;

    /**
     * @var bool Block until the actor is created
     */
    private bool $waitCreate;

    /**
     * @var float Wait timeout in seconds
     */
    private float $timeOut;

    /**
     * @param string      $actionClass
     * @param mixed       $data
     * @param string|null $name
     * @param string|null $parentName
     * @param string|null $routingKey
     * @param bool        $waitCreate
     * @param float       $timeOut
     */
    public function __construct(
        string $actionClass,
        $data = null,
        ?string $name = null,
        ?string $parentName = null,
        ?string $routingKey = null,
        bool $waitCreate = true,
        float $timeOut = 5.0
    ) {
        $this->actionClass = $actionClass;
        $this->data = $data;
        $this->name = $name;
        $this->parentName = $parentName;
        $this->routingKey = $routingKey;
        $this->waitCreate = $waitCreate;
        $this->timeOut = $timeOut;
    }

    /**
     * Convenience factory, Akka-style: Props::create(FooActor::class)
     *
     * @param string $actionClass
     * @param mixed  $data
     * @return self
     */
    public static function create(string $actionClass, $data = null): self
    {
        return new self($actionClass, $data);
    }

    public function getActionClass(): string
    {
        return $this->actionClass;
    }

    public function getData()
    {
        return $this->data;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function getParentName(): ?string
    {
        return $this->parentName;
    }

    public function getRoutingKey(): ?string
    {
        return $this->routingKey;
    }

    public function isWaitCreate(): bool
    {
        return $this->waitCreate;
    }

    public function getTimeOut(): float
    {
        return $this->timeOut;
    }

    public function withData($data): self
    {
        return new self($this->actionClass, $data, $this->name, $this->parentName, $this->routingKey, $this->waitCreate, $this->timeOut);
    }

    public function withName(?string $name): self
    {
        return new self($this->actionClass, $this->data, $name, $this->parentName, $this->routingKey, $this->waitCreate, $this->timeOut);
    }

    public function withParentName(?string $parentName): self
    {
        return new self($this->actionClass, $this->data, $this->name, $parentName, $this->routingKey, $this->waitCreate, $this->timeOut);
    }

    public function withRoutingKey(?string $routingKey): self
    {
        return new self($this->actionClass, $this->data, $this->name, $this->parentName, $routingKey, $this->waitCreate, $this->timeOut);
    }

    public function withWaitCreate(bool $waitCreate): self
    {
        return new self($this->actionClass, $this->data, $this->name, $this->parentName, $this->routingKey, $waitCreate, $this->timeOut);
    }

    public function withTimeOut(float $timeOut): self
    {
        return new self($this->actionClass, $this->data, $this->name, $this->parentName, $this->routingKey, $this->waitCreate, $timeOut);
    }

    /**
     * Flatten to the associative array accepted by ActorSystem::actorOf().
     *
     * @return array
     */
    public function toArray(): array
    {
        return [
            'data'       => $this->data,
            'name'       => $this->name,
            'parentName' => $this->parentName,
            'routingKey' => $this->routingKey,
            'waitCreate' => $this->waitCreate,
            'timeOut'    => $this->timeOut,
        ];
    }
}
