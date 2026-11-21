<?php

declare(strict_types=1);

namespace Framework;

final class Aop extends Ioc
{
    private array $interceptorCache = ['regex' => [], 'name' => [], 'type' => []];

    public function __construct(array|string $folders, array $config = [])
    {
        parent::__construct($folders, $config);

        if (isset($config['interceptors']) && is_array($config['interceptors']) && $config['interceptors'] !== []) {
            $this->loadInterceptors($config['interceptors']);
        }
    }

    public function intercept(string $beanName, string $interceptorName, string $methods = ''): static
    {
        $definition = ['name' => $interceptorName, 'methods' => $methods];
        $beanName = trim($beanName);

        if ($beanName !== '' && str_starts_with($beanName, '/') && str_ends_with($beanName, '/')) {
            $definition['regex'] = substr($beanName, 1, -1);
            $this->interceptorCache['regex'][] = $definition;
        } else {
            $this->interceptorCache['name'][$beanName] ??= [];
            $this->interceptorCache['name'][$beanName][] = $definition;
        }

        return $this;
    }

    public function interceptByType(string $type, string $interceptorName, string $methods = ''): static
    {
        $this->interceptorCache['type'][] = [
            'type' => $type,
            'name' => $interceptorName,
            'methods' => $methods,
        ];

        return $this;
    }

    protected function construct(string $className, array $arguments = []): object
    {
        $bean = parent::construct($className, $arguments);
        if (!$this->hasInterceptors($className)) {
            return $bean;
        }

        return new BeanProxy($bean, $this->getInterceptorsForBean($className));
    }

    protected function unwrapBean(object $bean): object
    {
        return $bean instanceof BeanProxy ? $bean->getTarget() : $bean;
    }

    private function getInterceptorsForBean(string $className): array
    {
        $beanName = lcfirst((new \ReflectionClass($className))->getShortName());
        $beanNames = $this->getAliases($className);
        array_unshift($beanNames, $beanName);
        $beanNames = array_values(array_unique($beanNames));

        $interceptors = [];
        foreach ($beanNames as $interceptedBeanName) {
            foreach ($this->interceptorCache['name'][$interceptedBeanName] ?? [] as $definition) {
                $interceptors[] = ['bean' => $this->getBean($definition['name']), 'methods' => $definition['methods']];
            }
        }

        foreach ($this->interceptorCache['regex'] as $definition) {
            foreach ($beanNames as $interceptedBeanName) {
                if (preg_match('/' . $definition['regex'] . '/i', $interceptedBeanName) === 1) {
                    $interceptors[] = ['bean' => $this->getBean($definition['name']), 'methods' => $definition['methods']];
                    break;
                }
            }
        }

        $beanTypes = $this->getBeanTypes($className);
        foreach ($this->interceptorCache['type'] as $definition) {
            if (in_array($definition['type'], $beanTypes, true)) {
                $interceptors[] = ['bean' => $this->getBean($definition['name']), 'methods' => $definition['methods']];
            }
        }

        return $interceptors;
    }

    private function hasInterceptors(string $className): bool
    {
        return $this->getInterceptorsForBean($className) !== [];
    }

    private function getAliases(string $className): array
    {
        $aliases = [];
        foreach ($this->getBeanInfo()['beanInfo'] as $key => $info) {
            if (($info['class'] ?? null) === $className) {
                $aliases[] = $key;
            }
        }
        return $aliases;
    }

    private function getBeanTypes(string $className): array
    {
        $reflection = new \ReflectionClass($className);
        $types = [];
        while ($reflection !== false) {
            $types[] = $reflection->getShortName();
            $types[] = $reflection->getName();
            $reflection = $reflection->getParentClass();
        }
        return $types;
    }

    private function loadInterceptors(array $interceptors): void
    {
        foreach ($interceptors as $interceptor) {
            if (isset($interceptor['beanName'])) {
                $this->intercept($interceptor['beanName'], $interceptor['interceptorName'], $interceptor['methods'] ?? '');
                continue;
            }

            $this->interceptByType($interceptor['type'], $interceptor['interceptorName'], $interceptor['methods'] ?? '');
        }
    }
}