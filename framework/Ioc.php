<?php

declare(strict_types=1);

namespace Framework;

use ReflectionClass;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionParameter;
use ReflectionProperty;
use RuntimeException;

class Ioc
{
    protected string $fw1Version = '0.1.0';
    protected string $di1Version = '0.1.0';
    protected array $folderArray = [];
    protected string $folderList = '';
    protected array $config = [];
    protected array $beanInfo = [];
    protected array $beanCache = [];
    protected array $resolutionCache = [];
    protected array $getBeanCache = [];
    protected string $constructingBeanName = '';
    protected array $listeners = [];
    protected array $transients = [];
    protected mixed $parent = null;
    protected bool $discoveryComplete = false;
    protected array $autoExclude = [
        '/framework/one.php',
        '/framework/ioc.php',
        '/framework/aop.php',
        '/framework/beanproxy.php',
    ];

    /**
     * The optional beanInitMethod runs before injection; initMethod runs after it.
     */
    public function __construct(array|string $folders, array $config = [])
    {
        $this->folderArray = is_array($folders) ? array_values($folders) : array_map('trim', explode(',', $folders));
        $this->folderList = implode(',', $this->folderArray);
        $this->config = $config;
        $this->setupFrameworkDefaults();

        if (isset($this->config['loadListener'])) {
            $this->onLoad($this->config['loadListener']);
        }
    }

    public function addAlias(string $aliasName, string $beanName): static
    {
        $this->discoverBeans();
        if (!isset($this->beanInfo[$beanName])) {
            throw new RuntimeException("bean not found: {$beanName}");
        }

        $this->beanInfo[$aliasName] = $this->beanInfo[$beanName];
        return $this;
    }

    public function addBean(string $beanName, mixed $beanValue): static
    {
        $this->beanInfo[$beanName] = [
            'name' => $beanName,
            'value' => $beanValue,
            'isSingleton' => true,
        ];
        $this->getBeanCache[$beanName] = $beanValue;
        return $this;
    }

    public function containsBean(string $beanName): bool
    {
        $this->discoverBeans();
        return isset($this->beanInfo[$beanName])
            || ($this->hasParent() && $this->parent->containsBean($beanName));
    }

    public function declare(string $beanName): DeclarationBuilder
    {
        return new DeclarationBuilder($this, $beanName);
    }

    public function declareBean(string $beanName, string $className, bool $isSingleton = true, array $overrides = []): static
    {
        $metadata = $this->metadata($className);
        $qualifier = $this->qualifierForClass($className);
        $this->beanInfo[$beanName] = [
            'name' => $beanName,
            'qualifier' => $qualifier,
            'isSingleton' => $isSingleton,
            'class' => $className,
            'metadata' => $metadata,
            'overrides' => $overrides,
        ];
        return $this;
    }

    public function factoryBean(string $beanName, mixed $factory, string $methodName = '', array $args = [], array $overrides = []): static
    {
        $this->beanInfo[$beanName] = [
            'name' => $beanName,
            'isSingleton' => false,
            'factory' => $factory,
            'method' => $methodName,
            'args' => $args,
            'overrides' => $overrides,
        ];
        return $this;
    }

    public function getBean(string $beanName, array $constructorArgs = []): mixed
    {
        $this->discoverBeans();

        if (array_key_exists($beanName, $this->getBeanCache) && $constructorArgs === []) {
            return $this->getBeanCache[$beanName];
        }

        if (isset($this->beanInfo[$beanName])) {
            $bean = $this->resolveBean($beanName, $constructorArgs);
            if ($this->isSingleton($beanName) && $constructorArgs === []) {
                $this->getBeanCache[$beanName] = $bean;
            }
            return $bean;
        }

        if ($this->hasParent()) {
            return $this->parent->getBean($beanName, $constructorArgs);
        }

        return $this->missingBean($beanName, '', false);
    }

    public function getBeanInfo(string $beanName = '', bool $flatten = false, string $regex = ''): array
    {
        $this->discoverBeans();

        if ($beanName !== '') {
            if (isset($this->beanInfo[$beanName])) {
                return $this->beanInfo[$beanName];
            }
            if ($this->hasParent()) {
                return $this->parent->getBeanInfo($beanName);
            }
            throw new RuntimeException("bean not found: {$beanName}");
        }

        $result = ['beanInfo' => $this->beanInfo];
        if ($this->hasParent()) {
            $parentInfo = $this->parent->getBeanInfo('', $flatten, $regex);
            if ($flatten || $regex !== '') {
                $result['beanInfo'] = array_merge($parentInfo['beanInfo'], $result['beanInfo']);
            } else {
                $result['parent'] = $parentInfo;
            }
        }

        if ($regex !== '') {
            $result['beanInfo'] = array_filter(
                $result['beanInfo'],
                static fn (string $name): bool => preg_match('/' . $regex . '/i', $name) === 1,
                ARRAY_FILTER_USE_KEY
            );
        }

        return $result;
    }

    public function getConfig(): array
    {
        return $this->config;
    }

    public function getVersion(): string
    {
        return $this->config['version'];
    }

    public function updateBeanDefinition(string $beanName, array $updates): void
    {
        if (!isset($this->beanInfo[$beanName])) {
            throw new RuntimeException("bean not found: {$beanName}");
        }

        $this->beanInfo[$beanName] = array_replace($this->beanInfo[$beanName], $updates);
    }

    public function hasParent(): bool
    {
        return $this->parent !== null;
    }

    public function isSingleton(string $beanName): bool
    {
        $this->discoverBeans();

        if (isset($this->beanInfo[$beanName])) {
            return $this->beanInfo[$beanName]['isSingleton'];
        }

        if ($this->hasParent()) {
            try {
                return $this->parent->isSingleton($beanName);
            } catch (\Throwable) {
                return false;
            }
        }

        return false;
    }

    public function injectProperties(object|string $bean, array $properties, bool $ignoreMissing = false): object
    {
        if (is_string($bean)) {
            if ($this->containsBean($bean)) {
                $bean = $this->getBean($bean);
            } else {
                $previousBeanName = $this->constructingBeanName;
                $this->constructingBeanName = '';
                try {
                    $bean = $this->construct($bean);
                } finally {
                    $this->constructingBeanName = $previousBeanName;
                }
            }
        }

        foreach ($properties as $property => $value) {
            $setter = 'set' . ucfirst($property);
            if (method_exists($bean, $setter)) {
                $bean->{$setter}($value);
                continue;
            }

            if (!$ignoreMissing) {
                throw new RuntimeException("setter not found: {$setter}");
            }
        }

        return $bean;
    }

    public function load(): static
    {
        $this->discoverBeans();
        $this->beanCache = [];
        $this->getBeanCache = [];

        foreach (array_keys($this->beanInfo) as $name) {
            if ($this->isSingleton($name)) {
                $this->getBean($name);
            }
        }

        return $this;
    }

    public function onLoad(mixed $listener): static
    {
        array_unshift($this->listeners, $listener);
        return $this;
    }

    public function setParent(mixed $parent): static
    {
        $this->parent = $parent;
        return $this;
    }

    protected function beanIsTransient(string $singleDir, string $dir, string $beanName): bool
    {
        return $singleDir === 'bean'
            || isset($this->transients[$dir])
            || (isset($this->config['singletonPattern']) && preg_match('/' . $this->config['singletonPattern'] . '/i', $beanName) !== 1)
            || (isset($this->config['transientPattern']) && preg_match('/' . $this->config['transientPattern'] . '/i', $beanName) === 1);
    }

    protected function construct(string $className, array $arguments = []): object
    {
        if ($arguments === []) {
            return new $className();
        }

        $reflection = new ReflectionClass($className);
        return $reflection->newInstanceArgs($arguments);
    }

    /**
     * Returns the object that dependency injection should target. AOP
     * subclasses return the proxied bean's underlying target so injection is
     * applied to the real bean rather than the proxy.
     */
    protected function unwrapBean(object $bean): object
    {
        return $bean;
    }

    protected function metadata(string $className): array
    {
        $reflection = new ReflectionClass($className);
        $constructorArgs = [];

        if (($constructor = $reflection->getConstructor()) instanceof ReflectionMethod) {
            foreach ($constructor->getParameters() as $parameter) {
                $constructorArgs[$parameter->getName()] = !$parameter->isOptional();
            }
        }

        $properties = [];
        foreach ($reflection->getProperties() as $property) {
            if ($property->isStatic()) {
                continue;
            }

            $properties[$property->getName()] = $property;
        }

        $setters = [];
        foreach ($reflection->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            if (str_starts_with($method->getName(), 'set') && $method->getNumberOfParameters() >= 1) {
                $name = lcfirst(substr($method->getName(), 3));
                $setters[$name] = $method->getName();
            }
        }

        return [
            'reflection' => $reflection,
            'constructor' => $constructorArgs,
            'properties' => $properties,
            'setters' => $setters,
        ];
    }

    protected function missingBean(string $beanName, string $resolvingBeanName = '', bool $dependency = true): mixed
    {
        if (($this->config['strict'] ?? false) || !$dependency) {
            $message = $resolvingBeanName !== ''
                ? "bean not found: {$beanName}; while resolving {$resolvingBeanName}"
                : "bean not found: {$beanName}";
            throw new RuntimeException($message);
        }

        return null;
    }

    protected function setupFrameworkDefaults(): void
    {
        $this->config += [
            'recurse' => true,
            'strict' => false,
            'exclude' => [],
            'omitDefaultedProperties' => true,
            'omitTypedProperties' => true,
            'omitDirectoryAliases' => false,
            'singulars' => [],
            'liberal' => false,
            'constants' => [],
            'transients' => [],
        ];

        foreach ($this->autoExclude as $pattern) {
            $this->config['exclude'][] = strtolower(str_replace('\\', '/', $pattern));
        }

        $this->beanInfo['beanFactory'] = ['value' => $this, 'isSingleton' => true];
        foreach ($this->config['constants'] as $beanName => $value) {
            $this->beanInfo[$beanName] = ['value' => $value, 'isSingleton' => true];
        }

        foreach ($this->config['transients'] as $transientFolder) {
            $this->transients[$transientFolder] = true;
        }

        if (isset($this->config['singletonPattern'], $this->config['transientPattern'])) {
            throw new RuntimeException('singletonPattern and transientPattern are mutually exclusive');
        }

        $this->config['version'] = $this->di1Version;
    }

    protected function setupInitMethod(string $name, object $bean): void
    {
    }

    protected function singular(string $plural): string
    {
        if (isset($this->config['singulars'][$plural])) {
            return $this->config['singulars'][$plural];
        }

        if (str_ends_with($plural, 'ies') && ($this->config['liberal'] ?? false)) {
            return substr($plural, 0, -3) . 'y';
        }

        return str_ends_with($plural, 's') ? substr($plural, 0, -1) : $plural;
    }

    private function discoverBeans(): void
    {
        if ($this->discoveryComplete) {
            return;
        }

        foreach ($this->folderArray as $folder) {
            $folder = trim($folder);
            if ($folder !== '') {
                $this->discoverBeansInFolder($folder);
            }
        }

        $this->discoveryComplete = true;
        $this->onLoadEvent();
    }

    private function discoverBeansInFolder(string $folder): void
    {
        $path = dirname(__DIR__) . '/' . trim($folder, '/');
        if (!is_dir($path)) {
            return;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if (!$file->isFile() || strtolower($file->getExtension()) !== 'php') {
                continue;
            }

            $normalized = strtolower(str_replace('\\', '/', $file->getPathname()));
            $excluded = false;
            foreach ($this->config['exclude'] as $pattern) {
                if (str_contains($normalized, strtolower($pattern))) {
                    $excluded = true;
                    break;
                }
            }
            if ($excluded) {
                continue;
            }

            $relative = str_replace(dirname(__DIR__) . '/', '', str_replace('\\', '/', $file->getPathname()));
            $className = $this->classNameFromPath($relative);
            if ($className === null || !class_exists($className)) {
                require_once $file->getPathname();
            }
            if ($className === null || !class_exists($className)) {
                continue;
            }

            $beanName = lcfirst(pathinfo($file->getFilename(), PATHINFO_FILENAME));
            $dir = basename($file->getPath());
            $singleDir = $this->singular($dir);
            $metadata = [
                'name' => $beanName,
                'qualifier' => $singleDir,
                'isSingleton' => !$this->beanIsTransient($singleDir, $dir, $beanName),
                'path' => $file->getPathname(),
                'class' => $className,
                'metadata' => $this->metadata($className),
            ];

            if ($this->config['omitDirectoryAliases']) {
                if (isset($this->beanInfo[$beanName])) {
                    throw new RuntimeException("{$beanName} is not unique");
                }
                $this->beanInfo[$beanName] = $metadata;
                continue;
            }

            if (!isset($this->beanInfo[$beanName])) {
                $this->beanInfo[$beanName] = $metadata;
            }

            $alias = $beanName . ucfirst($singleDir);
            if (isset($this->beanInfo[$alias])) {
                throw new RuntimeException("{$alias} is not unique");
            }
            $this->beanInfo[$alias] = $metadata;
        }
    }

    private function classNameFromPath(string $relativePath): ?string
    {
        $relativePath = str_replace('\\', '/', $relativePath);

        if (str_starts_with($relativePath, 'controllers/')) {
            return 'App\\Controllers\\' . str_replace('/', '\\', substr($relativePath, strlen('controllers/'), -4));
        }

        if (str_starts_with($relativePath, 'model/services/')) {
            return 'App\\Model\\Services\\' . str_replace('/', '\\', substr($relativePath, strlen('model/services/'), -4));
        }

        if (str_starts_with($relativePath, 'model/beans/')) {
            return 'App\\Model\\Beans\\' . str_replace('/', '\\', substr($relativePath, strlen('model/beans/'), -4));
        }

        return null;
    }

    private function qualifierForClass(string $className): string
    {
        if (str_contains($className, '\\Controllers\\')) {
            return 'controller';
        }
        if (str_contains($className, '\\Services\\')) {
            return 'service';
        }
        if (str_contains($className, '\\Beans\\')) {
            return 'bean';
        }
        return '';
    }

    private function resolveBean(string $beanName, array $constructorArgs = []): mixed
    {
        $info = $this->beanInfo[$beanName];

        if (isset($info['value'])) {
            return $info['value'];
        }

        if (isset($info['factory'])) {
            $factory = is_string($info['factory']) ? $this->getBean($info['factory']) : $info['factory'];
            $args = [];
            foreach ($info['args'] as $argName) {
                $args[] = $info['overrides'][$argName] ?? $this->getBean($argName);
            }

            return is_callable($factory)
                ? $factory(...$args)
                : $factory->{$info['method']}(...$args);
        }

        $className = $info['class'];
        $cacheKey = $info['name'] ?? $beanName;
        if ($info['isSingleton'] && isset($this->beanCache[$cacheKey])) {
            return $this->beanCache[$cacheKey];
        }

        $reflection = $info['metadata']['reflection'];
        $arguments = $this->resolveConstructorArguments($beanName, $reflection, $constructorArgs + ($info['overrides'] ?? []));
        $previousBeanName = $this->constructingBeanName;
        $this->constructingBeanName = $beanName;
        try {
            $bean = $this->construct($className, $arguments);
        } finally {
            $this->constructingBeanName = $previousBeanName;
        }
        $target = $this->unwrapBean($bean);

        if ($info['isSingleton']) {
            $this->beanCache[$cacheKey] = $bean;
        }

        try {
            if (isset($this->config['beanInitMethod']) && method_exists($target, $this->config['beanInitMethod'])) {
                $this->invokeLifecycleMethod($bean, $target, $this->config['beanInitMethod']);
            }
            $this->injectByProperties($beanName, $target, $info['metadata']['properties'], $info['overrides'] ?? []);
            $this->injectBySetters($beanName, $bean, $target, $info['metadata']['setters'], $info['overrides'] ?? []);
            $this->setupInitMethod($beanName, $target);

            if (isset($this->config['initMethod']) && method_exists($target, $this->config['initMethod'])) {
                $this->invokeLifecycleMethod($bean, $target, $this->config['initMethod']);
            }
        } catch (\Throwable $exception) {
            if ($info['isSingleton'] && ($this->beanCache[$cacheKey] ?? null) === $bean) {
                unset($this->beanCache[$cacheKey]);
            }

            throw $exception;
        }

        return $bean;
    }

    private function resolveConstructorArguments(string $beanName, ReflectionClass $reflection, array $overrides): array
    {
        $constructor = $reflection->getConstructor();
        if (!$constructor instanceof ReflectionMethod) {
            return [];
        }

        $arguments = [];
        foreach ($constructor->getParameters() as $parameter) {
            $name = $parameter->getName();
            if (array_key_exists($name, $overrides)) {
                $arguments[] = $overrides[$name];
                continue;
            }
            $dependencyName = $this->resolveConstructorBeanName($name);
            if ($dependencyName !== null) {
                $arguments[] = $this->getBean($dependencyName);
                continue;
            }
            if ($parameter->isDefaultValueAvailable()) {
                $arguments[] = $parameter->getDefaultValue();
                continue;
            }

            throw new RuntimeException("bean not found: {$name}; while resolving constructor arguments for {$beanName}");
        }

        return $arguments;
    }

    private function resolveConstructorBeanName(string $name): ?string
    {
        if ($this->containsBean($name)) {
            return $name;
        }

        $info = $this->getBeanInfo();
        do {
            $match = null;
            foreach (array_keys($info['beanInfo']) as $beanName) {
                $beanName = (string) $beanName;
                if (strcasecmp($name, $beanName) !== 0) {
                    continue;
                }
                if ($match !== null) {
                    throw new RuntimeException("ambiguous bean name: {$name}; matches {$match} and {$beanName}");
                }
                $match = $beanName;
            }
            if ($match !== null) {
                return $match;
            }
            $info = $info['parent'] ?? null;
        } while ($info !== null);

        return null;
    }

    private function injectByProperties(string $beanName, object $bean, array $properties, array $overrides): void
    {
        foreach ($properties as $name => $property) {
            $injectName = $this->resolvePropertyBeanName($property);
            if ($injectName === null) {
                continue;
            }

            $value = $overrides[$injectName] ?? $overrides[$name] ?? null;
            if ($value === null && $this->containsBean($injectName) && $this->isSingleton($injectName)) {
                $value = $this->getBean($injectName);
            }
            if ($value === null) {
                continue;
            }

            $property->setValue($bean, $value);
        }
    }

    private function injectBySetters(string $beanName, object $bean, object $target, array $setters, array $overrides): void
    {
        foreach ($setters as $property => $setter) {
            $value = $overrides[$property] ?? null;
            if ($value === null && $this->containsBean($property) && $this->isSingleton($property)) {
                $value = $this->getBean($property);
            }
            if ($value === null) {
                continue;
            }

            $this->invokeLifecycleMethod($bean, $target, $setter, [$value]);
        }
    }

    private function invokeLifecycleMethod(object $bean, object $target, string $method, array $arguments = []): mixed
    {
        if ($bean instanceof BeanProxy) {
            return $bean->invokeIntercepted($method, $arguments, [$target, $method], true);
        }

        return $target->{$method}(...$arguments);
    }

    private function resolvePropertyBeanName(ReflectionProperty $property): ?string
    {
        $attributes = $property->getAttributes(Inject::class);
        if ($attributes !== []) {
            $instance = $attributes[0]->newInstance();
            return $instance->bean ?? $property->getName();
        }

        if ($property->hasDefaultValue() && ($this->config['omitDefaultedProperties'] ?? true)) {
            return null;
        }

        $type = $property->getType();
        if ($type instanceof ReflectionNamedType && !$type->isBuiltin() && ($this->config['omitTypedProperties'] ?? true)) {
            return null;
        }

        return $property->getName();
    }

    private function onLoadEvent(): void
    {
        foreach ($this->listeners as $listener) {
            if (is_callable($listener)) {
                $listener($this);
                continue;
            }
            if (is_object($listener) && method_exists($listener, 'onLoad')) {
                $listener->onLoad($this);
                continue;
            }
            if (is_string($listener) && $this->containsBean($listener)) {
                $this->getBean($listener)->onLoad($this);
                continue;
            }

            throw new RuntimeException('invalid onLoad listener registered');
        }
    }
}

final class DeclarationBuilder
{
    private bool $built = false;

    public function __construct(private Ioc $factory, private string $beanName)
    {
    }

    public function aliasFor(string $beanName): self
    {
        $this->ensureAvailable();
        $this->built = true;
        $this->factory->addAlias($this->beanName, $beanName);
        return $this;
    }

    public function asValue(mixed $beanValue): self
    {
        $this->ensureAvailable();
        $this->built = true;
        $this->factory->addBean($this->beanName, $beanValue);
        return $this;
    }

    public function fromFactory(mixed $factory, string $methodName = ''): self
    {
        $this->ensureAvailable();
        $this->built = true;
        $this->factory->factoryBean($this->beanName, $factory, $methodName);
        return $this;
    }

    public function instanceOf(string $className): self
    {
        $this->ensureAvailable();
        $this->built = true;
        $this->factory->declareBean($this->beanName, $className);
        return $this;
    }

    public function asSingleton(): self
    {
        $this->factory->updateBeanDefinition($this->beanName, ['isSingleton' => true]);
        return $this;
    }

    public function asTransient(): self
    {
        $this->factory->updateBeanDefinition($this->beanName, ['isSingleton' => false]);
        return $this;
    }

    public function withArguments(array $args): self
    {
        $this->factory->updateBeanDefinition($this->beanName, ['args' => $args]);
        return $this;
    }

    public function withOverrides(array $overrides): self
    {
        $this->factory->updateBeanDefinition($this->beanName, ['overrides' => $overrides]);
        return $this;
    }

    public function done(): Ioc
    {
        return $this->factory;
    }

    private function ensureAvailable(): void
    {
        if ($this->built) {
            throw new RuntimeException('Declaration builder already completed!');
        }
    }
}