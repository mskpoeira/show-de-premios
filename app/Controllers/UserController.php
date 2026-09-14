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
    private const OPERATOR_ROLES = ['GERENTE_EVENTO','GERENTE_FINANCEIRO','CAIXA','OPERATOR','CONSULTA'];

    public function index(): void
    {
        if (!Auth::canManageUsers()) Response::redirect('/painel',null,'Acesso restrito.');
        $pdo=Database::getConnection();
        if (Auth::isMaster()) {
            $stmt=$pdo->query("SELECT id,name,login,role,active,created_at,updated_at FROM users ORDER BY CASE WHEN UPPER(role)='MASTER' THEN 0 ELSE 1 END,active DESC,name");
        } else {
            $stmt=$pdo->query("SELECT id,name,login,role,active,created_at,updated_at FROM users WHERE UPPER(role)<>'MASTER' ORDER BY active DESC,name");
        }
        View::render('users/index',['title'=>'Gestão de Operadores e Equipe — '.View::systemTitle(),'users'=>$stmt->fetchAll(PDO::FETCH_ASSOC),'isAdmin'=>Auth::isAdmin(),'isMaster'=>Auth::isMaster()]);
    }

    public function store(): void
    {
        if (!Auth::canManageUsers()) Response::redirect('/painel',null,'Permissão negada.');
        $name=trim((string)($_POST['name'] ?? ''));
        $login=strtolower(trim((string)($_POST['login'] ?? '')));
        $password=(string)($_POST['password'] ?? '');
        $role=strtoupper(trim((string)($_POST['role'] ?? 'CAIXA')));

        if (mb_strlen($name)<2 || !preg_match('/^[a-z0-9._@-]{3,100}$/i',$login)) Response::redirect('/configuracoes?tab=operadores',null,'Nome ou login inválido.');
        if (strlen($password)<10) Response::redirect('/configuracoes?tab=operadores',null,'A senha deve conter no mínimo 10 caracteres.');

        $allowed=self::OPERATOR_ROLES;
        if (Auth::isMaster()) $allowed[]='ADMIN';
        if (!in_array($role,$allowed,true)) Response::redirect('/configuracoes?tab=operadores',null,'Perfil de acesso não permitido.');

        $pdo=Database::getConnection();
        $check=$pdo->prepare('SELECT id FROM users WHERE LOWER(login)=? LIMIT 1');
        $check->execute([$login]);
        if ($check->fetchColumn()) Response::redirect('/configuracoes?tab=operadores',null,'Já existe usuário com esse login.');

        $stmt=$pdo->prepare("INSERT INTO users (name,login,password_hash,role,active,created_at,updated_at) VALUES (?,?,?,?,1,CURRENT_TIMESTAMP,CURRENT_TIMESTAMP)");
        $stmt->execute([$name,$login,Auth::hashPassword($password),$role]);
        $id=(int)$pdo->lastInsertId();
        AuditService::log('USER_CREATE','users',$id,null,['name'=>$name,'login'=>$login,'role'=>$role,'created_by'=>Auth::id()]);
        Response::redirect('/configuracoes?tab=operadores',"Usuário {$name} cadastrado com sucesso.");
    }

    public function update(): void
    {
        if (!Auth::canManageUsers()) Response::redirect('/painel',null,'Permissão negada.');
        $id=(int)($_POST['user_id'] ?? 0);
        $name=trim((string)($_POST['name'] ?? ''));
        $login=strtolower(trim((string)($_POST['login'] ?? '')));
        $role=strtoupper(trim((string)($_POST['role'] ?? '')));
        $password=(string)($_POST['password'] ?? '');
        if ($id<=0 || mb_strlen($name)<2) Response::redirect('/configuracoes?tab=operadores',null,'Dados inválidos.');

        $pdo=Database::getConnection();
        $targetStmt=$pdo->prepare('SELECT * FROM users WHERE id=? LIMIT 1');
        $targetStmt->execute([$id]);
        $target=$targetStmt->fetch(PDO::FETCH_ASSOC);
        if (!$target) Response::redirect('/configuracoes?tab=operadores',null,'Usuário não encontrado.');

        $targetRole=strtoupper((string)$target['role']);
        if ($targetRole==='MASTER') Response::redirect('/configuracoes?tab=operadores',null,'O Administrador Master é protegido e não pode ser alterado por esta tela.');
        if ($targetRole==='ADMIN' && !Auth::isMaster()) Response::redirect('/configuracoes?tab=operadores',null,'Somente o Master pode alterar administradores.');

        if ($login==='') $login=strtolower((string)$target['login']);
        if (!preg_match('/^[a-z0-9._@-]{3,100}$/i',$login)) Response::redirect('/configuracoes?tab=operadores',null,'Login inválido.');
        $check=$pdo->prepare('SELECT id FROM users WHERE LOWER(login)=? AND id<>? LIMIT 1');
        $check->execute([$login,$id]);
        if ($check->fetchColumn()) Response::redirect('/configuracoes?tab=operadores',null,'O login já está em uso.');

        $allowed=self::OPERATOR_ROLES;
        if (Auth::isMaster()) $allowed[]='ADMIN';
        $newRole=in_array($role,$allowed,true)?$role:$targetRole;
        if ($newRole==='ADMIN' && !Auth::isMaster()) $newRole=$targetRole;

        if ($password!=='') {
            if (strlen($password)<10) Response::redirect('/configuracoes?tab=operadores',null,'A nova senha deve conter no mínimo 10 caracteres.');
            $stmt=$pdo->prepare('UPDATE users SET name=?,login=?,role=?,password_hash=?,updated_at=CURRENT_TIMESTAMP WHERE id=?');
            $stmt->execute([$name,$login,$newRole,Auth::hashPassword($password),$id]);
        } else {
            $stmt=$pdo->prepare('UPDATE users SET name=?,login=?,role=?,updated_at=CURRENT_TIMESTAMP WHERE id=?');
            $stmt->execute([$name,$login,$newRole,$id]);
        }

        AuditService::log('USER_UPDATE','users',$id,['name'=>$target['name'],'login'=>$target['login'],'role'=>$targetRole],[
            'name'=>$name,'login'=>$login,'role'=>$newRole,'password_changed'=>$password!=='','updated_by'=>Auth::id()
        ]);
        Response::redirect('/configuracoes?tab=operadores',"Usuário {$name} atualizado com sucesso.");
    }

    public function toggleStatus(): void
    {
        if (!Auth::canManageUsers()) Response::redirect('/painel',null,'Permissão negada.');
        $id=(int)($_POST['user_id'] ?? 0);
        if ($id<=0) Response::redirect('/configuracoes?tab=operadores',null,'Usuário inválido.');
        if ($id===Auth::id()) Response::redirect('/configuracoes?tab=operadores',null,'Você não pode desativar sua própria sessão.');

        $pdo=Database::getConnection();
        $stmt=$pdo->prepare('SELECT * FROM users WHERE id=? LIMIT 1');
        $stmt->execute([$id]);
        $target=$stmt->fetch(PDO::FETCH_ASSOC);
        if (!$target) Response::redirect('/configuracoes?tab=operadores',null,'Usuário não encontrado.');

        $role=strtoupper((string)$target['role']);
        if ($role==='MASTER') Response::redirect('/configuracoes?tab=operadores',null,'O Administrador Master é protegido.');
        if ($role==='ADMIN' && !Auth::isMaster()) Response::redirect('/configuracoes?tab=operadores',null,'Somente o Master pode ativar/desativar administradores.');

        $newActive=(int)$target['active']===1?0:1;
        $pdo->prepare('UPDATE users SET active=?,updated_at=CURRENT_TIMESTAMP WHERE id=?')->execute([$newActive,$id]);
        AuditService::log('USER_STATUS_CHANGE','users',$id,['active'=>(int)$target['active']],['active'=>$newActive,'changed_by'=>Auth::id()]);
        Response::redirect('/configuracoes?tab=operadores',"Usuário {$target['name']} ".($newActive?'ativado':'desativado').' com sucesso.');
    }
}
