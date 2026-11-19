<?php

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

$app = new App\Application();
$response = $app->handleCurrentRequest();
$response->send();