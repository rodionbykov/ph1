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
            $key = strtolower($beanName);
            $this->interceptorCache['name'][$key] ??= [];
            $this->interceptorCache['name'][$key][] = $definition;
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
        $interceptors = $this->getInterceptorsForBean($className, $this->constructingBeanName);
        if ($interceptors === []) {
            return $bean;
        }

        $proxy = new BeanProxy($bean, $interceptors);
        if ($bean instanceof InternalCallAware) {
            $bean->attachInternalCallInterceptor(
                static fn (string $method, array $args, callable $original): mixed =>
                    $proxy->invokeIntercepted($method, $args, $original)
            );
        }

        return $proxy;
    }

    protected function unwrapBean(object $bean): object
    {
        return $bean instanceof BeanProxy ? $bean->getTarget() : $bean;
    }

    private function getInterceptorsForBean(string $className, string $beanName): array
    {
        $beanNames = $beanName === ''
            ? [lcfirst((new \ReflectionClass($className))->getShortName())]
            : $this->getAliases($className, $beanName);
        $beanNames = array_values(array_unique(array_map('strtolower', $beanNames)));

        $interceptors = [];
        foreach ($beanNames as $interceptedBeanName) {
            foreach ($this->interceptorCache['name'][strtolower($interceptedBeanName)] ?? [] as $definition) {
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
            if (in_array(strtolower($definition['type']), array_map('strtolower', $beanTypes), true)) {
                $interceptors[] = ['bean' => $this->getBean($definition['name']), 'methods' => $definition['methods']];
            }
        }

        return $interceptors;
    }

    private function getAliases(string $className, string $beanName): array
    {
        $aliases = [];
        $definition = $this->getBeanInfo($beanName);
        foreach ($this->getBeanInfo()['beanInfo'] as $key => $info) {
            if (($info['class'] ?? null) === $className && ($info['name'] ?? null) === ($definition['name'] ?? null)) {
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