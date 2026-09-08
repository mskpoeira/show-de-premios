<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Database;
use App\Core\Response;
use App\Core\View;

class AuditController
{
    public function index(): void
    {
        if (!Auth::isMasterAdmin()) {
            Response::redirect('/painel', null, 'Acesso à trilha de auditoria restrito exclusivamente ao Administrador Master.');
        }

        $pdo = Database::getConnection();

        $limit = 50;
        $page = max(1, (int)($_GET['page'] ?? 1));
        $offset = ($page - 1) * $limit;

        $stmtCount = $pdo->query("SELECT COUNT(*) FROM audit_logs");
        $totalLogs = (int)$stmtCount->fetchColumn();
        $totalPages = ceil($totalLogs / $limit);

        $stmt = $pdo->prepare("
            SELECT a.*, u.name as user_name, u.login as user_login
            FROM audit_logs a
            LEFT JOIN users u ON u.id = a.user_id
            ORDER BY a.created_at DESC
            LIMIT ? OFFSET ?
        ");
        $stmt->bindValue(1, $limit, \PDO::PARAM_INT);
        $stmt->bindValue(2, $offset, \PDO::PARAM_INT);
        $stmt->execute();
        $logs = $stmt->fetchAll();

        View::render('audit/index', [
            'title' => 'Auditoria do Sistema — Show de Prêmios',
            'logs' => $logs,
            'page' => $page,
            'totalPages' => $totalPages,
            'totalLogs' => $totalLogs,
        ]);
    }
}
