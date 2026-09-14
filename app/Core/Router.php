<?php

namespace App\Core;

class Router
{
    private static array $routes = [];

    public static function get(string $path, callable|array $handler, array $middlewares = []): void
    {
        self::add('GET', $path, $handler, $middlewares);
    }

    public static function post(string $path, callable|array $handler, array $middlewares = []): void
    {
        self::add('POST', $path, $handler, $middlewares);
    }

    private static function add(string $method, string $path, callable|array $handler, array $middlewares): void
    {
        $path = '/' . trim($path, '/');
        self::$routes[] = [
            'method' => $method,
            'path' => $path,
            'handler' => $handler,
            'middlewares' => $middlewares,
        ];
    }

    public static function dispatch(): void
    {
        $requestMethod = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
        if ($requestMethod === 'HEAD') {
            $requestMethod = 'GET';
        }

        $uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
        $base = trim((string)(getenv('APP_BASE_URL') ?: ''), '/');
        if ($base !== '') {
            $basePath = '/' . $base;
            if ($uri === $basePath) {
                $uri = '/';
            } elseif (str_starts_with($uri, $basePath . '/')) {
                $uri = substr($uri, strlen($basePath));
            }
        }
        $uri = '/' . trim($uri, '/');

        if ($requestMethod === 'POST') {
            $token = $_POST['_csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;
            if (!Csrf::validate(is_string($token) ? $token : null)) {
                self::csrfFailure();
            }
        }

        foreach (self::$routes as $route) {
            if ($route['method'] !== $requestMethod) {
                continue;
            }

            $pattern = preg_replace('/{([a-zA-Z0-9_]+)}/', '(?P<$1>[^/]+)', $route['path']);
            $pattern = '#^' . $pattern . '$#';

            if (!preg_match($pattern, $uri, $matches)) {
                continue;
            }

            $params = array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);

            foreach ($route['middlewares'] as $mw) {
                if (is_callable($mw)) {
                    $mw();
                } elseif (class_exists($mw) && method_exists($mw, 'handle')) {
                    (new $mw())->handle();
                }
            }

            $handler = $route['handler'];
            $argValues = array_values($params);

            if (is_array($handler)) {
                [$class, $action] = $handler;
                $controller = new $class();
                $controller->$action(...$argValues);
            } elseif (is_callable($handler)) {
                $handler(...$argValues);
            }
            return;
        }

        http_response_code(404);
        View::render('errors/404', ['title' => 'Página não encontrada']);
    }

    private static function csrfFailure(): never
    {
        http_response_code(403);
        $accept = strtolower((string)($_SERVER['HTTP_ACCEPT'] ?? ''));
        $isAjax = str_contains($accept, 'application/json') || isset($_POST['_ajax']);
        if ($isAjax) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'success' => false,
                'error' => 'Token CSRF inválido ou expirado. Atualize a página e tente novamente.'
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }

        header('Content-Type: text/plain; charset=utf-8');
        echo 'Aviso de Segurança: Token CSRF inválido ou expirado. Atualize a página e tente novamente.';
        exit;
    }
}
