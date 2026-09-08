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
        $requestMethod = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        if ($requestMethod === 'HEAD') {
            $requestMethod = 'GET';
        }
        $uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);

        // Normalize base url
        $base = rtrim(getenv('APP_BASE_URL') ?: '/showdepremios', '/');
        if (!empty($base) && str_starts_with($uri, $base)) {
            $uri = substr($uri, strlen($base));
        }

        $uri = '/' . trim($uri, '/');

        // Check CSRF for POST
        if ($requestMethod === 'POST') {
            $uriWithoutPrefix = '/' . trim($uri, '/');
            $isSpeakerApi = str_starts_with($uriWithoutPrefix, '/locutor/');
            if (!$isSpeakerApi) {
                $token = $_POST['_csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;
                if (!Csrf::validate($token)) {
                    http_response_code(403);
                    die("Aviso de Segurança: Token CSRF inválido ou expirado. Atualize a página e tente novamente.");
                }
            }
        }

        foreach (self::$routes as $route) {
            if ($route['method'] !== $requestMethod) {
                continue;
            }

            $pattern = preg_replace('/\{([a-zA-Z0-9_]+)\}/', '(?P<$1>[^/]+)', $route['path']);
            $pattern = '#^' . $pattern . '$#';

            if (preg_match($pattern, $uri, $matches)) {
                $params = array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);

                // Run Middlewares
                foreach ($route['middlewares'] as $mw) {
                    if (is_callable($mw)) {
                        $mw();
                    } elseif (class_exists($mw) && method_exists($mw, 'handle')) {
                        (new $mw())->handle();
                    }
                }

                // Execute handler
                $handler = $route['handler'];
                if (is_array($handler)) {
                    [$class, $action] = $handler;
                    $controller = new $class();
                    $controller->$action($params);
                } elseif (is_callable($handler)) {
                    $handler($params);
                }
                return;
            }
        }

        // 404
        http_response_code(404);
        View::render('errors/404', ['title' => 'Página não encontrada']);
    }
}
