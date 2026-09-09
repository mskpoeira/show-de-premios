<?php

namespace AppCore;

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
            Session::set('user_role', strtoupper($user['role']));
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
        return strtoupper((string)Session::get('user_role', 'CONSULTA'));
    }

    /**
     * MASTER: controle completo irrestrito
     */
    public static function isMaster(): bool
    {
        $role = self::role();
        $login = strtolower((string)Session::get('user_login', ''));
        return $role === 'MASTER' || $login === 'tcardozo' || ($login === 'admin' && in_array($role, ['MASTER', 'ADMIN'], true));
    }

    public static function isMasterAdmin(): bool
    {
        return self::isMaster();
    }

    /**
     * ADMINISTRADOR: operação administrativa geral
     */
    public static function isAdmin(): bool
    {
        return in_array(self::role(), ['MASTER', 'ADMIN', 'ADMINISTRADOR'], true) || self::isMaster();
    }

    /**
     * CAIXA: vendas, compradores, pagamentos, cartelas, validação e conferência
     */
    public static function isCaixa(): bool
    {
        return in_array(self::role(), ['MASTER', 'ADMIN', 'ADMINISTRADOR', 'CAIXA', 'OPERATOR'], true);
    }

    /**
     * CONSULTA: somente leitura autorizada
     */
    public static function isConsulta(): bool
    {
        return self::role() === 'CONSULTA';
    }

    public static function isOperator(): bool
    {
        return self::isCaixa();
    }

    public static function canManageUsers(): bool
    {
        return self::isAdmin();
    }

    public static function canManageSettings(): bool
    {
        return self::isAdmin();
    }

    public static function canViewAudit(): bool
    {
        return self::isAdmin();
    }

    public static function canBackup(): bool
    {
        return self::isMaster();
    }

    public static function roleLabel(?string $role = null): string
    {
        $role = strtoupper($role ?: self::role());
        return match ($role) {
            'MASTER' => '👑 MASTER',
            'ADMIN', 'ADMINISTRADOR' => '🛡️ ADMINISTRADOR',
            'CAIXA', 'OPERATOR' => '💵 CAIXA',
            'CONSULTA' => '👁️ CONSULTA',
            default => '👤 ' . $role,
        };
    }

    public static function roleBadgeColor(?string $role = null): string
    {
        $role = strtoupper($role ?: self::role());
        return match ($role) {
            'MASTER' => 'background: #0f766e; color: #ffffff;',
            'ADMIN', 'ADMINISTRADOR' => 'background: #0284c7; color: #ffffff;',
            'CAIXA', 'OPERATOR' => 'background: #d97706; color: #ffffff;',
            'CONSULTA' => 'background: #64748b; color: #ffffff;',
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
