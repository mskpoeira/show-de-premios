<?php

namespace App\Core;

use PDO;
use PDOException;

class Database
{
    private static ?PDO $instance = null;

    public static function getConnection(): PDO
    {
        if (self::$instance !== null) {
            return self::$instance;
        }

        $driver = getenv('DB_CONNECTION') ?: 'sqlite';

        try {
            if ($driver === 'sqlite') {
                $dbPath = getenv('DB_DATABASE') ?: __DIR__ . '/../../storage/database.sqlite';
                $dir = dirname($dbPath);
                if (!is_dir($dir)) {
                    mkdir($dir, 0755, true);
                }
                self::$instance = new PDO("sqlite:" . $dbPath);
                self::$instance->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                self::$instance->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
                self::$instance->exec("PRAGMA foreign_keys = ON;");
            } elseif ($driver === 'pgsql') {
                $host = getenv('DB_HOST') ?: '127.0.0.1';
                $port = getenv('DB_PORT') ?: '5432';
                $db   = getenv('DB_DATABASE') ?: 'showdepremios_db';
                $user = getenv('DB_USERNAME') ?: 'postgres';
                $pass = getenv('DB_PASSWORD') ?: '';

                $dsn = "pgsql:host={$host};port={$port};dbname={$db}";
                self::$instance = new PDO($dsn, $user, $pass, [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                ]);
            } else { // mysql / mariadb
                $host = getenv('DB_HOST') ?: '127.0.0.1';
                $port = getenv('DB_PORT') ?: '3306';
                $db   = getenv('DB_DATABASE') ?: 'showdepremios_db';
                $user = getenv('DB_USERNAME') ?: 'root';
                $pass = getenv('DB_PASSWORD') ?: '';

                $dsn = "mysql:host={$host};port={$port};dbname={$db};charset=utf8mb4";
                self::$instance = new PDO($dsn, $user, $pass, [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                ]);
            }
        } catch (PDOException $e) {
            die("Erro de conexão ao banco de dados: " . htmlspecialchars($e->getMessage()));
        }

        return self::$instance;
    }

    public static function getDriver(): string
    {
        return getenv('DB_CONNECTION') ?: 'sqlite';
    }
}
