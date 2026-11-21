<?php

declare(strict_types=1);

namespace Framework;

use BadMethodCallException;
use RuntimeException;

final class Facade
{
    private One $framework;

    public function __construct()
    {
        $framework = $GLOBALS['_fw1']['theFramework'] ?? null;
        if (!$framework instanceof One) {
            throw new RuntimeException('Unable to locate FW/1 for this request');
        }

        $this->framework = $framework;
    }

    public function __call(string $name, array $arguments): mixed
    {
        if (!method_exists($this->framework, $name)) {
            throw new BadMethodCallException("Undefined FW/1 facade method: {$name}");
        }

        return $this->framework->{$name}(...$arguments);
    }

    public function getBeanFactory(string $subsystem = ''): ?Ioc
    {
        return $this->framework->getBeanFactory($subsystem);
    }

    public function getFramework(): array
    {
        return $this->framework->getFramework();
    }
}