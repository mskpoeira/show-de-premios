<?php

namespace App\Core;

class RateLimiter
{
    public static function allow(string $bucket, int $maxAttempts, int $windowSeconds): bool
    {
        $dir = __DIR__ . '/../../storage/rate-limit';
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }

        $key = hash('sha256', $bucket);
        $path = $dir . '/' . $key . '.json';
        $now = time();
        $state = ['start' => $now, 'count' => 0];

        $fp = @fopen($path, 'c+');
        if (!$fp) {
            return true; // nunca derruba a aplicação por falha de rate limiter
        }

        try {
            if (!flock($fp, LOCK_EX)) {
                return true;
            }

            $raw = stream_get_contents($fp);
            if ($raw) {
                $decoded = json_decode($raw, true);
                if (is_array($decoded) && isset($decoded['start'], $decoded['count'])) {
                    $state = $decoded;
                }
            }

            if (($now - (int)$state['start']) >= $windowSeconds) {
                $state = ['start' => $now, 'count' => 0];
            }

            $state['count'] = (int)$state['count'] + 1;
            rewind($fp);
            ftruncate($fp, 0);
            fwrite($fp, json_encode($state));
            fflush($fp);
            flock($fp, LOCK_UN);

            return (int)$state['count'] <= $maxAttempts;
        } finally {
            fclose($fp);
        }
    }

    public static function clientKey(string $scope): string
    {
        $ip = (string)($_SERVER['REMOTE_ADDR'] ?? 'unknown');
        $ua = substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 120);
        return $scope . '|' . $ip . '|' . $ua;
    }
}
