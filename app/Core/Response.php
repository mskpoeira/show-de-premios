<?php

namespace App\Core;

class Response
{
    /** @param array<mixed> $data */
    public static function json(array $data, int $statusCode = 200): never
    {
        http_response_code($statusCode);
        header('Content-Type: application/json; charset=utf-8');
        header('X-Content-Type-Options: nosniff');
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR);
        exit;
    }

    public static function redirect(string $path, ?string $success = null, ?string $error = null): never
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
