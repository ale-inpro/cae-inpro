<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

use App\Core\Router;

spl_autoload_register(static function (string $class): void {
    $prefix = 'App\\';
    $baseDir = __DIR__ . '/../app/';

    if (!str_starts_with($class, $prefix)) {
        return;
    }

    $relativeClass = substr($class, strlen($prefix));
    $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';

    if (is_file($file)) {
        require $file;
    }
});

$routeConfig = require __DIR__ . '/../routes/web.php';
$routes = $routeConfig['routes'] ?? [];

$router = new Router();
foreach ($routes as $route) {
    $router->add($route['method'], $route['path'], $route['action']);
}

$method = $_POST['_method'] ?? $_SERVER['REQUEST_METHOD'] ?? 'GET';
$method = strtoupper((string) $method);

$requestUri = $_SERVER['REQUEST_URI'] ?? '/';
$scriptBase = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? ''));
$scriptBase = $scriptBase === '/' ? '' : rtrim($scriptBase, '/');

$path = parse_url($requestUri, PHP_URL_PATH) ?: '/';
if ($scriptBase !== '' && str_starts_with($path, $scriptBase)) {
    $path = substr($path, strlen($scriptBase)) ?: '/';
}

$router->dispatch($method, $path);

