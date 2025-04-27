<?php

namespace Yew\Core\Pool;

interface ConfigInterface
{
    public function getName(): string;

    public function setName(string $name): void;

    public function getPoolMaxNumber(): int;

    public function setPoolMaxNumber(int $poolMaxNumber): void;
}