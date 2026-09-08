<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Database;
use App\Core\Response;
use App\Core\View;
use App\Services\AuditService;

class AuthController
{
    public function showLogin(): void
    {
        if (!Auth::hasUsers()) {
            Response::redirect('/setup');
        }

        if (Auth::check()) {
            Response::redirect('/painel');
        }

        View::render('auth/login', ['title' => 'Login — Show de Prêmios'], false);
    }

    public function login(): void
    {
        $login = trim($_POST['login'] ?? '');
        $password = $_POST['password'] ?? '';

        if (empty($login) || empty($password)) {
            Response::redirect('/login', null, 'Informe login e senha.');
        }

        if (Auth::attempt($login, $password)) {
            AuditService::log('LOGIN', 'users', Auth::id(), null, ['login' => $login]);
            Response::redirect('/painel', 'Bem-vindo ao Show de Prêmios!');
        } else {
            Response::redirect('/login', null, 'Login ou senha incorretos.');
        }
    }

    public function logout(): void
    {
        if (Auth::check()) {
            AuditService::log('LOGOUT', 'users', Auth::id());
            Auth::logout();
        }
        Response::redirect('/login', 'Sessão encerrada com sucesso.');
    }

    public function showSetup(): void
    {
        if (Auth::hasUsers()) {
            Response::redirect('/login', null, 'O sistema já foi inicializado.');
        }

        View::render('auth/setup', ['title' => 'Instalação Inicial — Show de Prêmios'], false);
    }

    public function setupAdmin(): void
    {
        if (Auth::hasUsers()) {
            Response::redirect('/login', null, 'O sistema já possui administrador cadastrado.');
        }

        $name = trim($_POST['name'] ?? '');
        $login = trim($_POST['login'] ?? '');
        $password = $_POST['password'] ?? '';
        $passwordConfirm = $_POST['password_confirm'] ?? '';

        if (empty($name) || empty($login) || empty($password)) {
            Response::redirect('/setup', null, 'Preencha todos os campos obrigatórios.');
        }

        if (strlen($password) < 6) {
            Response::redirect('/setup', null, 'A senha deve conter no mínimo 6 caracteres.');
        }

        if ($password !== $passwordConfirm) {
            Response::redirect('/setup', null, 'As senhas informadas não coincidem.');
        }

        $pdo = Database::getConnection();
        $hash = Auth::hashPassword($password);

        $stmt = $pdo->prepare("INSERT INTO users (name, login, password_hash, role, active) VALUES (?, ?, ?, 'ADMIN', 1)");
        $stmt->execute([$name, $login, $hash]);

        AuditService::log('SETUP_ADMIN', 'users', (int)$pdo->lastInsertId(), null, ['login' => $login]);

        // Auto login
        Auth::attempt($login, $password);

        Response::redirect('/painel', 'Administrador configurado com sucesso! Bem-vindo ao sistema.');
    }
}
