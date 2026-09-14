<?php

namespace App\Core;

use PDO;

class Auth
{
    private static bool $revalidated = false;

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
        $stmt = $pdo->prepare("SELECT id, name, login, password_hash, role, active FROM users WHERE login = ? AND active = 1 LIMIT 1");
        $stmt->execute([$login]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user && self::verifyPassword($password, (string)$user['password_hash'])) {
            Session::regenerate();
            self::writeSession($user);
            self::$revalidated = true;
            return true;
        }

        return false;
    }

    public static function check(): bool
    {
        $userId = Session::get('user_id');
        if ($userId === null) {
            return false;
        }

        if (!self::$revalidated) {
            self::$revalidated = true;
            try {
                $pdo = Database::getConnection();
                $stmt = $pdo->prepare("SELECT id, name, login, role, active FROM users WHERE id = ? LIMIT 1");
                $stmt->execute([(int)$userId]);
                $user = $stmt->fetch(PDO::FETCH_ASSOC);

                if (!$user || (int)$user['active'] !== 1) {
                    self::logout();
                    return false;
                }

                self::writeSession($user);
            } catch (\Throwable $e) {
                error_log('[Auth revalidation] ' . $e->getMessage());
                self::logout();
                return false;
            }
        }

        return Session::get('user_id') !== null;
    }

    public static function user(): ?array
    {
        if (!self::check()) {
            return null;
        }

        return [
            'id' => (int)Session::get('user_id'),
            'name' => (string)Session::get('user_name'),
            'login' => (string)Session::get('user_login'),
            'role' => self::role(),
        ];
    }

    public static function id(): ?int
    {
        return self::check() ? (int)Session::get('user_id') : null;
    }

    public static function role(): string
    {
        return strtoupper((string)Session::get('user_role', 'CONSULTA'));
    }

    public static function isMaster(): bool
    {
        return self::role() === 'MASTER';
    }

    public static function isMasterAdmin(): bool
    {
        return self::isMaster();
    }

    public static function isAdmin(): bool
    {
        return in_array(self::role(), ['MASTER', 'ADMIN', 'ADMINISTRADOR'], true);
    }

    public static function isCaixa(): bool
    {
        return in_array(self::role(), [
            'MASTER', 'ADMIN', 'ADMINISTRADOR',
            'GERENTE_EVENTO', 'GERENTE_FINANCEIRO',
            'CAIXA', 'OPERATOR'
        ], true);
    }

    public static function isConsulta(): bool
    {
        return self::role() === 'CONSULTA';
    }

    public static function isOperator(): bool
    {
        return self::isCaixa();
    }

    public static function canOperateDraw(): bool
    {
        return in_array(self::role(), ['MASTER', 'ADMIN', 'ADMINISTRADOR', 'GERENTE_EVENTO', 'CAIXA', 'OPERATOR'], true);
    }

    public static function canConfirmPayments(): bool
    {
        return in_array(self::role(), ['MASTER', 'ADMIN', 'ADMINISTRADOR', 'GERENTE_FINANCEIRO', 'CAIXA', 'OPERATOR'], true);
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
            'GERENTE_EVENTO' => '🎤 GERENTE DE EVENTO',
            'GERENTE_FINANCEIRO' => '💰 GERENTE FINANCEIRO',
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
            'GERENTE_EVENTO' => 'background: #7c3aed; color: #ffffff;',
            'GERENTE_FINANCEIRO' => 'background: #059669; color: #ffffff;',
            'CAIXA', 'OPERATOR' => 'background: #d97706; color: #ffffff;',
            'CONSULTA' => 'background: #64748b; color: #ffffff;',
            default => 'background: #475569; color: #ffffff;',
        };
    }

    public static function logout(): void
    {
        Session::destroy();
        self::$revalidated = false;
    }

    public static function hasUsers(): bool
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->query("SELECT COUNT(*) FROM users");
        return (int)$stmt->fetchColumn() > 0;
    }

    private static function writeSession(array $user): void
    {
        Session::set('user_id', (int)$user['id']);
        Session::set('user_name', (string)$user['name']);
        Session::set('user_login', (string)$user['login']);
        Session::set('user_role', strtoupper((string)$user['role']));
    }
}
