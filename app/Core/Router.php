<?php
declare(strict_types=1);

namespace App\Core;

class Router
{
    private array $routes = [];
    private array $middlewareGroups = [];
    private array $currentMiddleware = [];

    public function get(string $path, $handler, array $middleware = []): void
    {
        $this->addRoute('GET', $path, $handler, array_merge($this->currentMiddleware, $middleware));
    }

    public function post(string $path, $handler, array $middleware = []): void
    {
        $this->addRoute('POST', $path, $handler, array_merge($this->currentMiddleware, $middleware));
    }

    public function group(array $middleware, callable $callback): void
    {
        $previous = $this->currentMiddleware;
        $this->currentMiddleware = array_merge($previous, $middleware);
        $callback($this);
        $this->currentMiddleware = $previous;
    }

    private function addRoute(string $method, string $path, $handler, array $middleware): void
    {
        $this->routes[] = [
            'method'     => $method,
            'path'       => $path,
            'handler'    => $handler,
            'middleware' => $middleware,
            'pattern'    => $this->buildPattern($path),
        ];
    }

    private function buildPattern(string $path): string
    {
        $pattern = preg_replace('/\{([a-zA-Z_][a-zA-Z0-9_]*)\}/', '(?P<$1>[^/]+)', $path);
        return '#^' . $pattern . '$#';
    }

    public function dispatch(): void
    {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $uri    = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);

        // Strip base path if app is in a subdirectory
        $scriptDir = dirname($_SERVER['SCRIPT_NAME']);
        if ($scriptDir !== '/' && str_starts_with($uri, $scriptDir)) {
            $uri = substr($uri, strlen($scriptDir));
        }
        $uri = '/' . ltrim($uri, '/');

        // Handle POST method override (_method field)
        if ($method === 'POST' && isset($_POST['_method'])) {
            $method = strtoupper($_POST['_method']);
        }

        $methodMatched = false;

        foreach ($this->routes as $route) {
            if (!preg_match($route['pattern'], $uri, $matches)) {
                continue;
            }

            $methodMatched = true;

            if ($route['method'] !== $method) {
                continue;
            }

            // Extract named params
            $params = array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);

            // Run middleware
            foreach ($route['middleware'] as $mw) {
                if (is_string($mw)) {
                    $mwInstance = new $mw();
                    $mwInstance->handle($params);
                } elseif (is_callable($mw)) {
                    $mw($params);
                }
            }

            // Dispatch handler
            $this->callHandler($route['handler'], $params);
            return;
        }

        if ($methodMatched) {
            http_response_code(405);
            echo '<h1>405 Método no permitido</h1>';
        } else {
            http_response_code(404);
            echo '<h1>404 Página no encontrada</h1>';
        }
    }

    private function callHandler($handler, array $params): void
    {
        if (is_callable($handler)) {
            call_user_func_array($handler, $params);
            return;
        }

        if (is_string($handler) && str_contains($handler, '@')) {
            [$class, $method] = explode('@', $handler, 2);
            // Resolve full class name
            if (!str_contains($class, '\\')) {
                $class = 'App\\Controllers\\' . $class;
            }
            $controller = new $class();
            call_user_func_array([$controller, $method], $params);
            return;
        }

        throw new \RuntimeException("Handler inválido: " . print_r($handler, true));
    }
}
