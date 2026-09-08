<?php

namespace App\Core;

class View
{
    public static function render(string $viewPath, array $data = [], bool $useLayout = true): void
    {
        extract($data);

        $flashSuccess = Session::getFlash('success');
        $flashError = Session::getFlash('error');
        $flashWarning = Session::getFlash('warning');
        $currentUser = Auth::user();
        $csrfField = Csrf::inputField();

        $contentFile = __DIR__ . '/../Views/' . $viewPath . '.php';

        if (!file_exists($contentFile)) {
            die("View não encontrada: " . htmlspecialchars($viewPath));
        }

        if ($useLayout) {
            ob_start();
            require $contentFile;
            $slot = ob_get_clean();

            require __DIR__ . '/../Views/layouts/main.php';
        } else {
            require $contentFile;
        }
    }

    public static function money(float|int|string|null $value): string
    {
        $floatVal = (float)($value ?? 0);
        return 'R$ ' . number_format($floatVal, 2, ',', '.');
    }

    public static function date(?string $date): string
    {
        if (!$date) {
            return '-';
        }
        $ts = strtotime($date);
        return $ts ? date('d/m/Y', $ts) : $date;
    }

    public static function datetime(?string $datetime): string
    {
        if (!$datetime) {
            return '-';
        }
        $ts = strtotime($datetime);
        return $ts ? date('d/m/Y H:i:s', $ts) : $datetime;
    }

    public static function percent(float|int|string|null $value): string
    {
        $floatVal = (float)($value ?? 0);
        return number_format($floatVal, 2, ',', '.') . '%';
    }

    public static function e(mixed $value): string
    {
        return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
    }

    public static function getBaseUrl(): string
    {
        $host = $_SERVER['HTTP_X_FORWARDED_HOST'] ?? $_SERVER['HTTP_HOST'] ?? '';
        if (!empty($host) && str_starts_with(strtolower($host), 'showdepremios.')) {
            return '';
        }
        return rtrim(getenv('APP_BASE_URL') ?: '/showdepremios', '/');
    }

    public static function url(string $path): string
    {
        $base = self::getBaseUrl();
        $path = ltrim($path, '/');
        if (empty($base)) {
            return '/' . $path;
        }
        return empty($path) ? $base : $base . '/' . $path;
    }

    private static ?string $cachedTitle = null;

    public static function systemTitle(): string
    {
        if (self::$cachedTitle !== null) {
            return self::$cachedTitle;
        }

        try {
            $pdo = Database::getConnection();
            $stmt = $pdo->prepare("SELECT value FROM settings WHERE key = 'system_title'");
            $stmt->execute();
            $val = $stmt->fetchColumn();
            self::$cachedTitle = !empty($val) ? (string)$val : 'Show de Prêmios';
        } catch (\Throwable $e) {
            self::$cachedTitle = 'Show de Prêmios';
        }

        return self::$cachedTitle;
    }
}
