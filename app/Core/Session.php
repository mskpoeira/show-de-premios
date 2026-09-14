<?php

namespace App\Core;

use RuntimeException;

class Session
{
    private const DEFAULT_LIFETIME = 28800;
    private const IDLE_TIMEOUT = 7200;

    public static function start(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            self::enforceIdleTimeout();
            return;
        }
        if (session_status() === PHP_SESSION_DISABLED) {
            throw new RuntimeException('Sessões PHP estão desabilitadas no servidor.');
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
        if (!session_start()) {
            throw new RuntimeException('Não foi possível iniciar a sessão.');
        }
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
        if (session_status() !== PHP_SESSION_ACTIVE) {
            self::start();
        }

        $_SESSION = [];
        if ((bool)ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            $cookieName = session_name();
            if ($cookieName !== false) {
                setcookie($cookieName, '', [
                    'expires' => time() - 42000,
                    'path' => $params['path'],
                    'domain' => $params['domain'],
                    'secure' => $params['secure'],
                    'httponly' => $params['httponly'],
                    'samesite' => $params['samesite'],
                ]);
            }
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
        return is_string($message) ? $message : null;
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
