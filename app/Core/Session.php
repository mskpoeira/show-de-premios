<?php

namespace App\Core;

class Session
{
    private const DEFAULT_LIFETIME = 28800; // 8 horas
    private const IDLE_TIMEOUT = 7200; // 2 horas sem atividade

    public static function start(): void
    {
        if (session_status() !== PHP_SESSION_NONE) {
            self::enforceIdleTimeout();
            return;
        }

        $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && strtolower((string)$_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https');
        $lifetime = max(900, (int)(getenv('SESSION_LIFETIME') ?: self::DEFAULT_LIFETIME));

        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');

        session_set_cookie_params([
            'lifetime' => $lifetime,
            'path' => '/',
            'domain' => '',
            'secure' => $isHttps,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);

        session_name('SHOWDEPREMIOS_SESSION');
        session_start();
        self::enforceIdleTimeout();
    }

    public static function set(string $key, mixed $value): void
    {
        self::start();
        $_SESSION[$key] = $value;
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        self::start();
        return $_SESSION[$key] ?? $default;
    }

    public static function remove(string $key): void
    {
        self::start();
        unset($_SESSION[$key]);
    }

    public static function destroy(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            self::start();
        }

        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', [
                'expires' => time() - 42000,
                'path' => $params['path'] ?: '/',
                'domain' => $params['domain'] ?? '',
                'secure' => (bool)($params['secure'] ?? false),
                'httponly' => (bool)($params['httponly'] ?? true),
                'samesite' => $params['samesite'] ?? 'Lax',
            ]);
        }
        session_destroy();
    }

    public static function regenerate(): void
    {
        self::start();
        session_regenerate_id(true);
        $_SESSION['_last_activity'] = time();
    }

    public static function setFlash(string $type, string $message): void
    {
        self::set('_flash_' . $type, $message);
    }

    public static function getFlash(string $type): ?string
    {
        $key = '_flash_' . $type;
        $message = self::get($key);
        if ($message !== null) {
            self::remove($key);
        }
        return $message;
    }

    private static function enforceIdleTimeout(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            return;
        }

        $now = time();
        $last = (int)($_SESSION['_last_activity'] ?? $now);
        if (!empty($_SESSION['user_id']) && ($now - $last) > self::IDLE_TIMEOUT) {
            self::destroy();
            return;
        }
        $_SESSION['_last_activity'] = $now;
    }
}
