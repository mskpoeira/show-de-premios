<?php

namespace App\Core;

class Response
{
    public static function json(array $data, int $statusCode = 200): void
    {
        http_response_code($statusCode);
        header('Content-Type: application/json; charset=utf-8');
        header('X-Content-Type-Options: nosniff');
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        exit;
    }

    public static function redirect(string $path, ?string $success = null, ?string $error = null): void
    {
        if ($success) {
            Session::setFlash('success', $success);
        }
        if ($error) {
            Session::setFlash('error', $error);
        }

        $url = View::url($path);
        header("Location: {$url}");
        exit;
    }
}
