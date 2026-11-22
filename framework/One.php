<?php

declare(strict_types=1);

namespace Framework;

use RuntimeException;
use Throwable;

class One
{
    protected string $fw1Version = '4.3.2';
    protected array $framework = [];
    protected array $requestState = [];
    protected array $requestContext = [];
    protected array $privateContext = [];
    protected array $controllers = [];
    protected Response $response;
    protected ?Ioc $beanFactory = null;
    protected bool $applicationInitialized = false;
    protected array $renderData = [];
    protected ?string $overrideViewAction = null;
    protected ?string $overrideLayoutAction = null;
    protected bool $suppressOtherLayouts = false;
    protected bool $layoutEnabled = true;
    private ?bool $pendingLayoutEnabled = null;
    private bool $requestActive = false;

    public function __construct(array $config = [])
    {
        $this->response = new Response();
        $this->framework = $this->defaultFramework();
        $this->framework = array_replace($this->framework, $config);
        $this->framework['version'] = $this->fw1Version;
    }

    public function handleCurrentRequest(): Response
    {
        try {
            $this->onApplicationStart();
            $this->onRequestStart($_SERVER['SCRIPT_NAME'] ?? 'index.php');
            $this->onRequest($_SERVER['SCRIPT_NAME'] ?? 'index.php');
            $this->onRequestEnd();
        } catch (Throwable $exception) {
            $this->handleException($exception);
        } finally {
            $this->requestActive = false;
        }

        return $this->response;
    }

    public function abortController(): never
    {
        throw new AbortControllerException('abortController() called');
    }

    public function actionSpecifiesSubsystem(string $action): bool
    {
        return str_contains($action, $this->framework['subsystemDelimiter']);
    }

    public function buildCustomURL(string $uri): string
    {
        return rtrim($this->getBaseURL(), '/') . '/' . ltrim($uri, '/');
    }

    public function buildURL(string $action = '.', string $path = '', string|array $queryString = ''): string
    {
        if ($action === '.') {
            $action = $this->getSubsystemSectionAndItem();
        }

        $action = $this->getFullyQualifiedAction($action);
        $path = $path !== '' ? $path : $this->getBaseURL();
        $query = $this->normalizeQueryString($queryString);

        $isHomeAction = $action === $this->getFullyQualifiedAction($this->framework['home']);
        $url = $path;
        if (!$isHomeAction) {
            $separator = str_contains($path, '?') ? '&' : '?';
            $url .= $separator . $this->framework['action'] . '=' . rawurlencode($action);
        }

        if ($query !== '') {
            $separator = str_contains($url, '?') ? '&' : '?';
            $url .= $separator . ltrim($query, '?&');
        }

        return $url;
    }

    public function controller(string $action): void
    {
        $action = $this->getFullyQualifiedAction($action);
        $subsystem = $this->getSubsystem($action);
        $section = $this->getSection($action);
        $item = $this->getItem($action);
        $controller = $this->getController($section, $subsystem);
        $key = $subsystem !== '' ? $subsystem . ':' . $section : $section;

        $this->controllers[] = [
            'controller' => $controller,
            'section' => $section,
            'subsystem' => $subsystem,
            'item' => $item,
            'key' => $key,
            'action' => $action,
        ];
    }

    public function disableLayout(): void
    {
        $this->layoutEnabled = false;
        if (!$this->requestActive) {
            $this->pendingLayoutEnabled = false;
        }
    }

    public function enableLayout(): void
    {
        $this->layoutEnabled = true;
        if (!$this->requestActive) {
            $this->pendingLayoutEnabled = true;
        }
    }

    public function getBeanFactory(string $subsystem = ''): ?Ioc
    {
        return $this->beanFactory;
    }

    /**
     * Returns the name of the default subsystem, mirroring one.cfc.
     */
    public function getDefaultSubsystem(): string
    {
        if (!$this->usingSubsystems()) {
            return '';
        }

        if (array_key_exists('subsystem', $this->requestState)) {
            return (string) $this->requestState['subsystem'];
        }

        if (($this->framework['defaultSubsystem'] ?? '') === '') {
            throw new SubsystemNotSpecifiedException(
                'No subsystem specified and no default configured. ' .
                'When using subsystems, every request should specify a subsystem or ' .
                'defaultSubsystem should be configured.'
            );
        }

        return $this->framework['defaultSubsystem'];
    }

    public function getFramework(): array
    {
        return $this->framework;
    }

    public function getFullyQualifiedAction(?string $action = null): string
    {
        $action = $action ?? ($this->requestState['action'] ?? $this->framework['home']);
        if (!$this->framework['noLowerCase']) {
            $action = strtolower($action);
        }

        $subsystem = $this->getSubsystem($action);
        $section = $this->getSection($action);
        $item = $this->getItem($action);

        return $subsystem !== ''
            ? $subsystem . $this->framework['subsystemDelimiter'] . $section . '.' . $item
            : $section . '.' . $item;
    }

    public function getItem(?string $action = null): string
    {
        $action = $action ?? ($this->requestState['action'] ?? $this->framework['home']);
        $action = $this->stripSubsystem($action);
        if (!str_contains($action, '.')) {
            return $this->framework['defaultItem'];
        }
        return explode('.', $action, 2)[1] ?: $this->framework['defaultItem'];
    }

    public function &getRequestContext(): array
    {
        return $this->requestContext;
    }

    public function getSection(?string $action = null): string
    {
        $action = $action ?? ($this->requestState['action'] ?? $this->framework['home']);
        $action = $this->stripSubsystem($action);
        return explode('.', $action, 2)[0] ?: $this->framework['defaultSection'];
    }

    public function getSectionAndItem(?string $action = null): string
    {
        return $this->getSection($action) . '.' . $this->getItem($action);
    }

    public function getSubsystem(?string $action = null): string
    {
        $action = $action ?? ($this->requestState['action'] ?? $this->framework['home']);
        if (!str_contains($action, $this->framework['subsystemDelimiter'])) {
            return '';
        }
        return explode($this->framework['subsystemDelimiter'], $action, 2)[0];
    }

    public function getSubsystemSectionAndItem(?string $action = null): string
    {
        $action = $this->getFullyQualifiedAction($action);
        $subsystem = $this->getSubsystem($action);
        return ($subsystem !== '' ? $subsystem . $this->framework['subsystemDelimiter'] : '') . $this->getSectionAndItem($action);
    }

    public function layout(string $path, string $body): string
    {
        $layoutPath = $this->parseViewOrLayoutPath($path, 'layout');
        return $this->internalRender($layoutPath, ['body' => $body]);
    }

    public function onApplicationStart(): void
    {
        $this->setupRequestDefaults();
        $this->setupApplicationWrapper();
    }

    public function onMissingView(array $rc, array $prc): string
    {
        throw new RuntimeException('FW1.viewNotFound');
    }

    public function onRequest(string $targetPath): void
    {
        try {
            if (method_exists($this, 'before')) {
                $this->before($this->requestContext, $this->privateContext);
            }

            $seen = [];
            foreach ($this->controllers as $tuple) {
                if (!isset($seen[$tuple['key']])) {
                    $seen[$tuple['key']] = true;
                    $this->doController($tuple, 'before');
                }
                $this->doController($tuple, $tuple['item']);
            }

            for ($i = count($this->controllers) - 1; $i >= 0; --$i) {
                $tuple = $this->controllers[$i];
                if (($seen[$tuple['key']] ?? false) === true) {
                    $seen[$tuple['key']] = false;
                    $this->doController($tuple, 'after');
                }
            }

            if (method_exists($this, 'after')) {
                $this->after($this->requestContext, $this->privateContext);
            }
        } catch (AbortControllerException) {
        }

        if ($this->renderData !== []) {
            $this->renderDataWithContentType();
            $this->setupResponseWrapper();
            return;
        }

        $this->buildViewQueue();
        $this->setupView($this->requestContext, $this->privateContext);
        $viewPath = $this->requestState['view'] ?? null;

        if ($viewPath === null) {
            $body = $this->onMissingView($this->requestContext, $this->privateContext);
        } else {
            $body = $this->internalRender($viewPath);
        }

        foreach ($this->buildLayoutQueue() as $layoutPath) {
            if (!$this->layoutEnabled) {
                break;
            }
            $body = $this->internalRender($layoutPath, ['body' => $body]);
        }

        $this->response->setBody($body);
        $this->setupResponseWrapper();
    }

    public function onRequestEnd(): void
    {
        $this->requestActive = false;
    }

    public function onRequestStart(string $targetPath): void
    {
        $this->setupRequestDefaults();
        $this->pendingLayoutEnabled = null;
        $this->requestActive = true;
        $this->setupApplicationWrapper();
        $this->setupRequestWrapper();
    }

    public function populate(object $cfc, string $keys = '', bool $trustKeys = false, bool $trim = false, bool $deep = false, array $properties = []): object
    {
        $properties = $properties !== [] ? $properties : $this->requestContext;
        $selected = $keys === '' ? array_keys($properties) : array_map('trim', explode(',', $keys));

        foreach ($selected as $property) {
            if (!array_key_exists($property, $properties)) {
                continue;
            }

            $setter = 'set' . ucfirst($property);
            if (!method_exists($cfc, $setter) && !$trustKeys) {
                continue;
            }

            $value = $properties[$property];
            if ($trim && is_string($value)) {
                $value = trim($value);
            }

            if (method_exists($cfc, $setter)) {
                $cfc->{$setter}($value);
            }
        }

        return $cfc;
    }

    public function redirect(
        string $action,
        string $preserve = 'none',
        string $append = 'none',
        string $path = '',
        string|array $queryString = '',
        string $statusCode = '302',
        string $header = ''
    ): void {
        $targetUrl = $this->buildURL($action, $path, $queryString);
        $this->response->setStatusCode((int) $statusCode);
        $this->response->setHeader($header !== '' ? $header : 'Location', $targetUrl);
        $this->abortController();
    }

    public function redirectCustomURL(string $uri, string $preserve = 'none', string $statusCode = '302', string $header = ''): void
    {
        $targetUrl = $this->buildCustomURL($uri);
        $this->response->setStatusCode((int) $statusCode);
        $this->response->setHeader($header !== '' ? $header : 'Location', $targetUrl);
        $this->abortController();
    }

    public function renderData(string|callable $type = '', mixed $data = '', int $statusCode = 200, string $jsonpCallback = ''): RendererBuilder
    {
        $this->renderData = [
            'type' => $type,
            'data' => $data,
            'statusCode' => $statusCode,
            'statusText' => '',
            'jsonpCallback' => $jsonpCallback,
            'headers' => [],
        ];

        return new RendererBuilder($this->renderData);
    }

    public function renderer(): RendererBuilder
    {
        if ($this->renderData === []) {
            $this->renderData();
        }

        return new RendererBuilder($this->renderData);
    }

    public function setBeanFactory(?Ioc $beanFactory): void
    {
        $this->beanFactory = $beanFactory;
    }

    public function setLayout(string $action, bool $suppressOtherLayouts = false): void
    {
        $this->overrideLayoutAction = $this->getFullyQualifiedAction($action);
        $this->suppressOtherLayouts = $suppressOtherLayouts;
    }

    public function setupApplication(): void
    {
    }

    public function setupEnvironment(string $env): void
    {
    }

    protected function setupRequest(): void
    {
    }

    public function setupResponse(array $rc, array $prc): void
    {
    }

    public function setupSession(): void
    {
    }

    public function setupSubsystem(string $subsystem): void
    {
    }

    public function setupTraceRender(string $output = 'html'): void
    {
    }

    public function setupView(array $rc, array $prc): void
    {
    }

    public function setView(string $action): void
    {
        $this->overrideViewAction = $this->getFullyQualifiedAction($action);
    }

    public function usingSubsystems(): bool
    {
        return $this->framework['usingSubsystems'];
    }

    public function view(string $path, array $args = [], mixed $missingView = ''): string
    {
        $viewPath = $this->parseViewOrLayoutPath($path, 'view');
        if (is_file($viewPath)) {
            return $this->internalRender($viewPath, $args);
        }

        if ($missingView !== '') {
            return (string) $missingView;
        }

        return $this->onMissingView($this->requestContext, $this->privateContext);
    }

    protected function defaultFramework(): array
    {
        return [
            'action' => 'action',
            'usingSubsystems' => false,
            'defaultSubsystem' => 'home',
            'defaultSection' => 'main',
            'defaultItem' => 'default',
            'subsystemDelimiter' => ':',
            'siteWideLayoutSubsystem' => 'common',
            'subsystems' => [],
            'home' => 'main.default',
            'error' => 'main.error',
            'reload' => 'reload',
            'password' => 'true',
            'reloadApplicationOnEveryRequest' => false,
            'preserveKeyURLKey' => 'fw1pk',
            'maxNumContextsPreserved' => 10,
            'baseURL' => 'index.php',
            'generateSES' => false,
            'SESOmitIndex' => false,
            'unhandledExtensions' => ['php'],
            'unhandledPaths' => ['/flex2gateway'],
            'unhandledErrorCaught' => false,
            'applicationKey' => 'framework.one',
            'cacheFileExists' => false,
            'routes' => [],
            'routesCaseSensitive' => true,
            'noLowerCase' => false,
            'trace' => false,
            'controllersFolder' => 'controllers',
            'layoutsFolder' => 'layouts',
            'subsystemsFolder' => 'subsystems',
            'viewsFolder' => 'views',
            'diOverrideAllowed' => false,
            'diEngine' => 'di1',
            'diLocations' => ['model', 'controllers'],
            'diConfig' => [],
            'diComponent' => Ioc::class,
            'decodeRequestBody' => false,
            'preflightOptions' => false,
            'optionsAccessControl' => [
                'origin' => '*',
                'headers' => 'Accept, Authorization, Content-Type',
                'credentials' => true,
                'maxAge' => 1728000,
            ],
            'environments' => [],
        ];
    }

    protected function getBaseURL(): string
    {
        return $this->framework['baseURL'] === 'useCgiScriptName'
            ? ($_SERVER['SCRIPT_NAME'] ?? '/index.php')
            : $this->framework['baseURL'];
    }

    protected function getController(string $section, string $subsystem = ''): object
    {
        $beanName = $section . 'Controller';
        if ($this->beanFactory?->containsBean($beanName)) {
            return $this->beanFactory->getBean($beanName);
        }

        $className = 'App\\Controllers\\' . ucfirst($section);
        if (!class_exists($className)) {
            throw new RuntimeException("Controller not found: {$className}");
        }

        return new $className($this);
    }

    protected function handleException(Throwable $exception): void
    {
        try {
            $this->requestState['failedAction'] = $this->requestState['action'] ?? null;
            $this->requestState['exception'] = $exception;
            $this->controllers = [];
            $this->renderData = [];
            $this->overrideLayoutAction = null;
            $this->overrideViewAction = null;
            $this->layoutEnabled = true;
            $this->requestState['action'] = $this->framework['error'];
            $this->requestContext = array_merge($this->requestContext, ['exception' => $exception]);
            $this->controller($this->framework['error']);
            $this->onRequest('');
        } catch (Throwable $fatal) {
            $message = htmlspecialchars($exception->getMessage(), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $detail = htmlspecialchars($fatal->getMessage(), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $this->response->setStatusCode(500);
            $this->response->setBody("<h1>FW/1 PHP Port Error</h1><p>{$message}</p><p>{$detail}</p>");
        }
    }

    protected function normalizeQueryString(string|array $queryString): string
    {
        if (is_array($queryString)) {
            return http_build_query($queryString);
        }

        return ltrim($queryString, '?');
    }

    protected function parseViewOrLayoutPath(string $path, string $type): string
    {
        $path = trim($path);
        if (str_contains($path, '/')) {
            $relative = $path . '.php';
        } else {
            $relative = str_replace('.', '/', $this->getSectionAndItem($path)) . '.php';
        }

        $base = dirname(__DIR__) . '/' . $this->framework[$type . 'sFolder'];
        return $base . '/' . ltrim($relative, '/');
    }

    protected function renderDataWithContentType(): void
    {
        foreach ($this->renderData['headers'] as $header) {
            $this->response->setHeader($header['name'], $header['value']);
        }

        $this->response->setStatusCode($this->renderData['statusCode']);
        $type = $this->renderData['type'];
        $data = $this->renderData['data'];

        if (is_callable($type)) {
            $result = $type($this->renderData);
            $this->response->setContentType($result['contentType']);
            $this->response->setBody($result['output']);
            return;
        }

        switch ($type) {
            case 'json':
                $this->response->setContentType('application/json; charset=utf-8');
                $this->response->setBody(json_encode($data, JSON_THROW_ON_ERROR));
                break;
            case 'rawjson':
                $this->response->setContentType('application/json; charset=utf-8');
                $this->response->setBody((string) $data);
                break;
            case 'text':
                $this->response->setContentType('text/plain; charset=utf-8');
                $this->response->setBody((string) $data);
                break;
            case 'html':
            case '':
                $this->response->setContentType('text/html; charset=utf-8');
                $this->response->setBody((string) $data);
                break;
            default:
                throw new RuntimeException("Unsupported renderData type: {$type}");
        }
    }

    protected function setupApplicationWrapper(): void
    {
        if ($this->applicationInitialized) {
            return;
        }

        $this->applicationInitialized = true;
        $GLOBALS['_fw1']['theFramework'] = $this;
        if ($this->framework['diEngine'] !== 'none') {
            $component = $this->framework['diComponent'];
            if ($this->framework['diEngine'] === 'aop1') {
                $component = Aop::class;
            }
            if ($this->framework['diEngine'] === 'di1') {
                $component = Ioc::class;
            }

            $factory = new $component($this->framework['diLocations'], $this->framework['diConfig']);
            $factory->addBean('fw', $this);
            $factory->addBean('framework', $this);
            $this->setBeanFactory($factory);
        }

        $this->setupApplication();
    }

    protected function setupRequestDefaults(): void
    {
        $this->response = new Response();
        $this->requestState = [];
        $this->controllers = [];
        $this->renderData = [];
        $this->overrideLayoutAction = null;
        $this->overrideViewAction = null;
        $this->suppressOtherLayouts = false;
        $this->layoutEnabled = $this->pendingLayoutEnabled ?? true;
        $this->requestContext = array_merge($_GET, $_POST);
        $this->privateContext = [];
    }

    protected function setupRequestWrapper(): void
    {
        $this->requestState['action'] = $this->resolveAction();
        $this->requestContext[$this->framework['action']] = $this->requestState['action'];
        $this->setupRequest();
        $this->controller($this->requestState['action']);
    }

    protected function setupResponseWrapper(): void
    {
        $this->setupResponse($this->requestContext, $this->privateContext);
    }

    private function buildLayoutQueue(): array
    {
        $action = $this->overrideLayoutAction ?? $this->requestState['action'];
        $section = $this->getSection($action);
        $item = $this->getItem($action);
        $layouts = [];

        $candidates = [
            dirname(__DIR__) . '/' . $this->framework['layoutsFolder'] . '/' . $section . '/' . $item . '.php',
            dirname(__DIR__) . '/' . $this->framework['layoutsFolder'] . '/' . $section . '.php',
            dirname(__DIR__) . '/' . $this->framework['layoutsFolder'] . '/default.php',
        ];

        foreach ($candidates as $candidate) {
            if (is_file($candidate)) {
                $layouts[] = $candidate;
                if ($this->suppressOtherLayouts) {
                    break;
                }
            }
        }

        return $layouts;
    }

    private function buildViewQueue(): void
    {
        $action = $this->overrideViewAction ?? $this->requestState['action'];
        $viewPath = dirname(__DIR__) . '/' . $this->framework['viewsFolder'] . '/' . str_replace('.', '/', $this->getSectionAndItem($action)) . '.php';
        if (is_file($viewPath)) {
            $this->requestState['view'] = $viewPath;
        }
    }

    private function doController(array $tuple, string $method): void
    {
        $controller = $tuple['controller'];
        if (!method_exists($controller, $method)) {
            return;
        }

        $controller->{$method}($this->requestContext, $this->privateContext);
    }

    private function internalRender(string $path, array $extra = []): string
    {
        $rc = &$this->requestContext;
        $prc = &$this->privateContext;
        $request = &$this->requestState;
        $framework = $this->framework;
        extract($extra, EXTR_SKIP);

        ob_start();
        include $path;
        return (string) ob_get_clean();
    }

    private function resolveAction(): string
    {
        $action = $this->requestContext[$this->framework['action']] ?? '';
        if ($action === '' && isset($_SERVER['PATH_INFO'])) {
            $parts = array_values(array_filter(explode('/', trim((string) $_SERVER['PATH_INFO'], '/')), static fn (string $part): bool => $part !== ''));
            if ($parts !== []) {
                $section = $parts[0] ?? $this->framework['defaultSection'];
                $item = $parts[1] ?? $this->framework['defaultItem'];
                $action = $section . '.' . $item;
                for ($i = 2; $i < count($parts); $i += 2) {
                    $key = $parts[$i];
                    $value = $parts[$i + 1] ?? '';
                    $this->requestContext[$key] = $value;
                }
            }
        }

        return $action !== '' ? $this->getFullyQualifiedAction($action) : $this->framework['home'];
    }

    private function stripSubsystem(string $action): string
    {
        if (!str_contains($action, $this->framework['subsystemDelimiter'])) {
            return $action;
        }

        return explode($this->framework['subsystemDelimiter'], $action, 2)[1];
    }
}

final class AbortControllerException extends RuntimeException
{
}

/**
 * Port of the CFML FW1.subsystemNotSpecified exception.
 */
final class SubsystemNotSpecifiedException extends RuntimeException
{
}