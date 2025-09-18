<?php

namespace Yew\Core\Pool;

interface ConfigInterface
{
    public function getName(): string;

    public function setName(string $name): void;

    public function getOptions(): array;

    public function setOptions(array $options): void;

}