<?php

namespace App\Core;

use PDO;

class Auth
{
    public static function hashPassword(string $password): string
    {
        if (defined('PASSWORD_ARGON2ID')) {
            return password_hash($password, PASSWORD_ARGON2ID);
        }
        return password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
    }

    public static function verifyPassword(string $password, string $hash): bool
    {
        return password_verify($password, $hash);
    }

    public static function attempt(string $login, string $password): bool
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("SELECT * FROM users WHERE login = ? AND active = 1 LIMIT 1");
        $stmt->execute([$login]);
        $user = $stmt->fetch();

        if ($user && self::verifyPassword($password, $user['password_hash'])) {
            Session::regenerate();
            Session::set('user_id', $user['id']);
            Session::set('user_name', $user['name']);
            Session::set('user_login', $user['login']);
            Session::set('user_role', $user['role']);
            return true;
        }

        return false;
    }

    public static function check(): bool
    {
        return Session::get('user_id') !== null;
    }

    public static function user(): ?array
    {
        if (!self::check()) {
            return null;
        }
        return [
            'id' => Session::get('user_id'),
            'name' => Session::get('user_name'),
            'login' => Session::get('user_login'),
            'role' => Session::get('user_role'),
        ];
    }

    public static function id(): ?int
    {
        return Session::get('user_id');
    }

    public static function role(): string
    {
        return Session::get('user_role', 'VIEWER');
    }

    public static function isAdmin(): bool
    {
        return self::role() === 'ADMIN';
    }

    public static function isMasterAdmin(): bool
    {
        $login = strtolower((string)Session::get('user_login', ''));
        return $login === 'tcardozo' || ($login === 'admin' && self::isAdmin());
    }

    public static function isOperator(): bool
    {
        return in_array(self::role(), ['ADMIN', 'OPERATOR', 'GERENTE_EVENTO', 'GERENTE_FINANCEIRO', 'CAIXA'], true);
    }

    public static function isEventManager(): bool
    {
        return in_array(self::role(), ['ADMIN', 'GERENTE_EVENTO'], true);
    }

    public static function isFinancialManager(): bool
    {
        return in_array(self::role(), ['ADMIN', 'GERENTE_FINANCEIRO'], true);
    }

    public static function isCashier(): bool
    {
        return in_array(self::role(), ['ADMIN', 'GERENTE_FINANCEIRO', 'CAIXA'], true);
    }

    public static function canManageUsers(): bool
    {
        return in_array(self::role(), ['ADMIN', 'GERENTE_EVENTO', 'GERENTE_FINANCEIRO'], true);
    }

    public static function roleLabel(?string $role = null): string
    {
        $role = $role ?: self::role();
        return match ($role) {
            'ADMIN' => '🛡️ Administrador Master',
            'GERENTE_EVENTO' => '🎪 Gerente do Evento',
            'GERENTE_FINANCEIRO' => '💼 Gerente Financeiro',
            'CAIXA' => '💵 Operador de Caixa',
            'OPERATOR' => '⚙️ Operador Geral',
            default => '👁️ Visualizador',
        };
    }

    public static function roleBadgeColor(?string $role = null): string
    {
        $role = $role ?: self::role();
        return match ($role) {
            'ADMIN' => 'background: #4f46e5; color: #ffffff;',
            'GERENTE_EVENTO' => 'background: #0284c7; color: #ffffff;',
            'GERENTE_FINANCEIRO' => 'background: #059669; color: #ffffff;',
            'CAIXA' => 'background: #d97706; color: #ffffff;',
            default => 'background: #475569; color: #ffffff;',
        };
    }

    public static function logout(): void
    {
        Session::destroy();
    }

    public static function hasUsers(): bool
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->query("SELECT COUNT(*) FROM users");
        return (int)$stmt->fetchColumn() > 0;
    }
}
