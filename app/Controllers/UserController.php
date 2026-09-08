<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Database;
use App\Core\Response;
use App\Core\View;
use App\Services\AuditService;
use PDO;

class UserController
{
    public function index(): void
    {
        if (!Auth::canManageUsers()) {
            Response::redirect('/painel', null, 'Acesso restrito: você não tem permissão para gerenciar operadores.');
        }

        $pdo = Database::getConnection();
        $isAdmin = Auth::isAdmin();

        $currentLogin = strtolower(Auth::user()['login'] ?? '');
        $isMasterTcardozo = ($currentLogin === 'tcardozo');

        // Ninguém visualiza o administrador master tcardozo (somente ele próprio quando logado)
        if ($isMasterTcardozo) {
            $stmt = $pdo->query("SELECT id, name, login, role, active, created_at, updated_at FROM users ORDER BY active DESC, name ASC");
        } elseif ($isAdmin) {
            $stmt = $pdo->query("SELECT id, name, login, role, active, created_at, updated_at FROM users WHERE LOWER(login) != 'tcardozo' ORDER BY active DESC, name ASC");
        } else {
            $stmt = $pdo->query("SELECT id, name, login, role, active, created_at, updated_at FROM users WHERE LOWER(login) != 'tcardozo' AND role != 'ADMIN' ORDER BY active DESC, name ASC");
        }
        $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

        View::render('users/index', [
            'title' => 'Gestão de Operadores e Equipe — ' . View::systemTitle(),
            'users' => $users,
            'isAdmin' => $isAdmin,
        ]);
    }

    public function store(): void
    {
        if (!Auth::canManageUsers()) {
            Response::redirect('/painel', null, 'Permissão negada.');
        }

        $name = trim($_POST['name'] ?? '');
        $login = strtolower(trim($_POST['login'] ?? ''));
        $password = trim($_POST['password'] ?? '');
        $role = trim($_POST['role'] ?? 'CAIXA');

        if (strlen($name) < 2) {
            Response::redirect('/configuracoes?tab=operadores', null, 'O nome do operador deve ter no mínimo 2 caracteres.');
        }
        if (strlen($login) < 3) {
            Response::redirect('/configuracoes?tab=operadores', null, 'O login do operador deve ter no mínimo 3 caracteres.');
        }
        if (strlen($password) < 6) {
            Response::redirect('/configuracoes?tab=operadores', null, 'A senha do operador deve ter no mínimo 6 caracteres.');
        }

        // Validação estrita de cargos
        $validRoles = ['CAIXA', 'GERENTE_FINANCEIRO', 'GERENTE_EVENTO', 'OPERATOR'];
        if (Auth::isAdmin()) {
            $validRoles[] = 'ADMIN';
        }

        if (!in_array($role, $validRoles, true)) {
            // Não-admins não podem criar cargos de gerência ou admin
            $role = 'CAIXA';
        }

        $pdo = Database::getConnection();

        // Verificar login duplicado
        $stmtCheck = $pdo->prepare("SELECT id FROM users WHERE LOWER(login) = ? LIMIT 1");
        $stmtCheck->execute([$login]);
        if ($stmtCheck->fetch()) {
            Response::redirect('/configuracoes?tab=operadores', null, 'Já existe um operador cadastrado com o login "' . htmlspecialchars($login) . '".');
        }

        $passwordHash = Auth::hashPassword($password);

        $stmtInsert = $pdo->prepare("
            INSERT INTO users (name, login, password_hash, role, active, created_at, updated_at)
            VALUES (?, ?, ?, ?, 1, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
        ");
        $stmtInsert->execute([$name, $login, $passwordHash, $role]);
        $newId = (int)$pdo->lastInsertId();

        AuditService::log('USER_CREATE', 'users', $newId, null, [
            'name' => $name,
            'login' => $login,
            'role' => $role,
        ]);

        Response::redirect('/configuracoes?tab=operadores', "Operador(a) {$name} ({$role}) cadastrado(a) com sucesso!");
    }

    public function update(): void
    {
        if (!Auth::canManageUsers()) {
            Response::redirect('/painel', null, 'Permissão negada.');
        }

        $id = (int)($_POST['user_id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $login = strtolower(trim($_POST['login'] ?? ''));
        $role = trim($_POST['role'] ?? '');
        $password = trim($_POST['password'] ?? '');

        if ($id <= 0 || strlen($name) < 2) {
            Response::redirect('/configuracoes?tab=operadores', null, 'Dados inválidos para alteração de operador.');
        }

        $pdo = Database::getConnection();
        $stmtTarget = $pdo->prepare("SELECT * FROM users WHERE id = ? LIMIT 1");
        $stmtTarget->execute([$id]);
        $targetUser = $stmtTarget->fetch(PDO::FETCH_ASSOC);

        if (!$targetUser) {
            Response::redirect('/configuracoes?tab=operadores', null, 'Operador não encontrado.');
        }

        // Se login não foi informado pelo formulário (ex: campo readonly sem name), preserva o login existente
        if (empty($login)) {
            $login = strtolower(trim($targetUser['login']));
        }

        if (strlen($login) < 3) {
            Response::redirect('/configuracoes?tab=operadores', null, 'Login inválido para o operador.');
        }

        // Se o alvo for ADMIN e o usuário atual NÃO for ADMIN, bloqueia imediatamente
        if ($targetUser['role'] === 'ADMIN' && !Auth::isAdmin()) {
            Response::redirect('/configuracoes?tab=operadores', null, 'Operação não permitida.');
        }

        // Validação de login duplicado em outro ID
        $stmtCheck = $pdo->prepare("SELECT id FROM users WHERE LOWER(login) = ? AND id != ? LIMIT 1");
        $stmtCheck->execute([$login, $id]);
        if ($stmtCheck->fetch()) {
            Response::redirect('/configuracoes?tab=operadores', null, 'O login "' . htmlspecialchars($login) . '" já está sendo utilizado por outro usuário.');
        }

        // Definição de cargo
        if (Auth::isAdmin()) {
            $validRoles = ['ADMIN', 'GERENTE_EVENTO', 'GERENTE_FINANCEIRO', 'CAIXA', 'OPERATOR'];
            $newRole = in_array($role, $validRoles, true) ? $role : $targetUser['role'];
        } else {
            // Gerentes só podem alterar para CAIXA ou manter o cargo atual do operador
            $newRole = in_array($role, ['CAIXA', 'OPERATOR'], true) ? $role : $targetUser['role'];
        }

        if (!empty($password)) {
            if (strlen($password) < 6) {
                Response::redirect('/configuracoes?tab=operadores', null, 'A nova senha deve ter no mínimo 6 caracteres.');
            }
            $passwordHash = Auth::hashPassword($password);
            $stmtUpdate = $pdo->prepare("
                UPDATE users 
                SET name = ?, login = ?, role = ?, password_hash = ?, updated_at = CURRENT_TIMESTAMP 
                WHERE id = ?
            ");
            $stmtUpdate->execute([$name, $login, $newRole, $passwordHash, $id]);
        } else {
            $stmtUpdate = $pdo->prepare("
                UPDATE users 
                SET name = ?, login = ?, role = ?, updated_at = CURRENT_TIMESTAMP 
                WHERE id = ?
            ");
            $stmtUpdate->execute([$name, $login, $newRole, $id]);
        }

        AuditService::log('USER_UPDATE', 'users', $id, [
            'name' => $targetUser['name'],
            'login' => $targetUser['login'],
            'role' => $targetUser['role'],
        ], [
            'name' => $name,
            'login' => $login,
            'role' => $newRole,
            'password_changed' => !empty($password),
        ]);

        Response::redirect('/configuracoes?tab=operadores', "Dados do operador(a) {$name} atualizados com sucesso!");
    }

    public function toggleStatus(): void
    {
        if (!Auth::canManageUsers()) {
            Response::redirect('/painel', null, 'Permissão negada.');
        }

        $id = (int)($_POST['user_id'] ?? 0);

        // Não pode desativar o próprio usuário logado
        if ($id === Auth::id()) {
            Response::redirect('/configuracoes?tab=operadores', null, 'Você não pode desativar o seu próprio usuário logado.');
        }

        $pdo = Database::getConnection();
        $stmtTarget = $pdo->prepare("SELECT * FROM users WHERE id = ? LIMIT 1");
        $stmtTarget->execute([$id]);
        $targetUser = $stmtTarget->fetch(PDO::FETCH_ASSOC);

        if (!$targetUser) {
            Response::redirect('/configuracoes?tab=operadores', null, 'Operador não encontrado.');
        }

        // Administrador invisível não pode ser tocado por não-admins
        if ($targetUser['role'] === 'ADMIN' && !Auth::isAdmin()) {
            Response::redirect('/configuracoes?tab=operadores', null, 'Operação não permitida.');
        }

        $newActive = $targetUser['active'] ? 0 : 1;
        $stmtUpdate = $pdo->prepare("UPDATE users SET active = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
        $stmtUpdate->execute([$newActive, $id]);

        AuditService::log('USER_STATUS_CHANGE', 'users', $id, [
            'active' => $targetUser['active']
        ], [
            'active' => $newActive
        ]);

        $statusLabel = $newActive ? 'ativado' : 'desativado';
        Response::redirect('/configuracoes?tab=operadores', "Operador(a) {$targetUser['name']} {$statusLabel} com sucesso!");
    }
}
