<?php

declare(strict_types=1);

namespace App\Core;

use ReflectionMethod;

final class Router
{
    /** @var array<int, array{method: string, path: string, action: callable|array{0: class-string, 1: string}}> */
    private array $routes = [];

    /**
     * @param callable|array{0: class-string, 1: string} $action
     */
    public function add(string $method, string $path, callable|array $action): void
    {
        $this->routes[] = [
            'method' => strtoupper($method),
            'path' => $this->normalizePath($path),
            'action' => $action,
        ];
    }

    public function dispatch(string $method, string $path): void
    {
        $method = strtoupper($method);
        $path = $this->normalizePath($path);

        foreach ($this->routes as $route) {
            if ($route['method'] !== $method) {
                continue;
            }

            $params = $this->match($route['path'], $path);
            if ($params === null) {
                continue;
            }

            $this->runAction($route['action'], $params);
            return;
        }

        http_response_code(404);
        header('Content-Type: text/plain; charset=utf-8');
        echo '404 - Route not found';
    }

    /**
     * @return array<string, string>|null
     */
    private function match(string $routePath, string $requestPath): ?array
    {
        $pattern = preg_replace_callback(
            '/\{([a-zA-Z_][a-zA-Z0-9_]*)\}/',
            static fn(array $m): string => '(?P<' . $m[1] . '>[^/]+)',
            $routePath
        );

        $regex = '#^' . $pattern . '$#';
        if (!preg_match($regex, $requestPath, $matches)) {
            return null;
        }

        $params = [];
        foreach ($matches as $key => $value) {
            if (is_string($key)) {
                $params[$key] = $value;
            }
        }

        return $params;
    }

    /**
     * @param callable|array{0: class-string, 1: string} $action
     * @param array<string, string> $params
     */
    private function runAction(callable|array $action, array $params): void
    {
        if (is_callable($action)) {
            $action($params);
            return;
        }

        [$class, $method] = $action;
        $controller = new $class();
        $ref = new ReflectionMethod($class, $method);
        if ($ref->getNumberOfParameters() > 0) {
            $controller->$method($params);
        } else {
            $controller->$method();
        }
    }

    private function normalizePath(string $path): string
    {
        $path = trim($path);
        if ($path === '') {
            return '/';
        }

        $path = '/' . trim($path, '/');
        return $path === '//' ? '/' : $path;
    }
}

