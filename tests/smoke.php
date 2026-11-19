<?php

declare(strict_types=1);

require dirname(__DIR__) . '/bootstrap.php';

$_GET = ['action' => 'main.default'];
$_POST = [];
$_SERVER['SCRIPT_NAME'] = '/index.php';
$_SERVER['REQUEST_METHOD'] = 'GET';
unset($_SERVER['PATH_INFO']);

$app = new App\Application();
$response = $app->handleCurrentRequest();
$body = $response->getBody();

if (!str_contains($body, 'This is the default view for FW/1.')) {
    fwrite(STDERR, "Default view text missing\n");
    exit(1);
}

if (!str_contains($body, 'Powered by FW/1 version 4.3.2.')) {
    fwrite(STDERR, "Layout text missing\n");
    exit(1);
}

if (!preg_match('/This request took ([0-9]+)ms\./', $body, $matches)) {
    fwrite(STDERR, "Request duration missing\n");
    exit(1);
}

if ((int) $matches[1] > 10000) {
    fwrite(STDERR, "Request duration is not realistic\n");
    exit(1);
}

fwrite(STDOUT, "smoke test passed\n");