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
        $login = trim((string)($_POST['login'] ?? ''));
        $password = (string)($_POST['password'] ?? '');

        if ($login === '' || $password === '') {
            Response::redirect('/login', null, 'Informe login e senha.');
        }

        if (Auth::attempt($login, $password)) {
            AuditService::log('LOGIN', 'users', Auth::id(), null, ['login' => $login]);
            Response::redirect('/painel', 'Bem-vindo ao Show de Prêmios!');
        }

        usleep(300000);
        Response::redirect('/login', null, 'Login ou senha incorretos.');
    }

    public function logout(): void
    {
        if (Auth::check()) {
            AuditService::log('LOGOUT', 'users', Auth::id());
        }
        Auth::logout();
        Response::redirect('/login', 'Sessão encerrada com sucesso.');
    }

    public function showSetup(): void
    {
        if (Auth::hasUsers()) {
            Response::redirect('/login', null, 'O sistema já foi inicializado.');
        }

        View::render('auth/setup', [
            'title' => 'Instalação Inicial — Show de Prêmios',
            'setupTokenRequired' => trim((string)getenv('APP_SETUP_TOKEN')) !== '',
        ], false);
    }

    public function setupAdmin(): void
    {
        if (Auth::hasUsers()) {
            Response::redirect('/login', null, 'O sistema já possui administrador cadastrado.');
        }

        $expectedSetupToken = trim((string)getenv('APP_SETUP_TOKEN'));
        if ($expectedSetupToken !== '') {
            $provided = trim((string)($_POST['setup_token'] ?? ''));
            if ($provided === '' || !hash_equals($expectedSetupToken, $provided)) {
                Response::redirect('/setup', null, 'Token de instalação inválido.');
            }
        }

        $name = trim((string)($_POST['name'] ?? ''));
        $login = trim((string)($_POST['login'] ?? ''));
        $password = (string)($_POST['password'] ?? '');
        $passwordConfirm = (string)($_POST['password_confirm'] ?? '');

        if ($name === '' || $login === '' || $password === '') {
            Response::redirect('/setup', null, 'Preencha todos os campos obrigatórios.');
        }

        if (strlen($password) < 10) {
            Response::redirect('/setup', null, 'A senha inicial deve conter no mínimo 10 caracteres.');
        }

        if ($password !== $passwordConfirm) {
            Response::redirect('/setup', null, 'As senhas informadas não coincidem.');
        }

        $pdo = Database::getConnection();
        $hash = Auth::hashPassword($password);

        $stmt = $pdo->prepare("INSERT INTO users (name, login, password_hash, role, active) VALUES (?, ?, ?, 'MASTER', 1)");
        $stmt->execute([$name, $login, $hash]);
        $newId = (int)$pdo->lastInsertId();

        AuditService::log('SETUP_MASTER', 'users', $newId, null, ['login' => $login]);
        Auth::attempt($login, $password);

        Response::redirect('/painel', 'Administrador Master configurado com sucesso.');
    }
}
