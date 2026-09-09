<?php

namespace AppControllers;

use AppCoreAuth;
use AppCoreDatabase;
use AppCoreResponse;
use AppCoreView;
use PDO;

class AuditController
{
    /**
     * Trilha geral de auditoria do sistema
     */
    public function index(): void
    {
        if (!Auth::isAdmin()) {
            Response::redirect('/painel', null, 'Acesso restrito a administradores.');
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
        $stmt->bindValue(1, $limit, PDO::PARAM_INT);
        $stmt->bindValue(2, $offset, PDO::PARAM_INT);
        $stmt->execute();
        $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

        View::render('audit/index', [
            'title' => 'Auditoria do Sistema — Show de Prêmios',
            'logs' => $logs,
            'page' => $page,
            'totalPages' => $totalPages,
            'totalLogs' => $totalLogs,
        ]);
    }

    /**
     * Painel de Auditoria de Consultas a Dados Pessoais (LGPD / Segurança)
     * Rota: /auditoria/consultas
     */
    public function personalDataAccessLogs(): void
    {
        if (!Auth::isAdmin()) {
            Response::redirect('/painel', null, 'Acesso restrito a administradores.');
        }

        $pdo = Database::getConnection();

        $userFilter = trim($_GET['user'] ?? '');
        $roleFilter = trim($_GET['role'] ?? '');
        $ticketFilter = trim($_GET['ticket'] ?? '');
        $buyerFilter = trim($_GET['buyer'] ?? '');
        $cpfFilter = preg_replace('/\D/', '', $_GET['cpf'] ?? '');
        $dateFrom = trim($_GET['date_from'] ?? '');
        $dateTo = trim($_GET['date_to'] ?? '');
        $ipFilter = trim($_GET['ip'] ?? '');
        $resultFilter = trim($_GET['result'] ?? '');

        $where = ["1=1"];
        $params = [];

        if (!empty($userFilter)) {
            $where[] = "tal.user_name LIKE ?";
            $params[] = "%{$userFilter}%";
        }
        if (!empty($roleFilter)) {
            $where[] = "tal.user_role = ?";
            $params[] = $roleFilter;
        }
        if (!empty($ticketFilter)) {
            $where[] = "t.ticket_number LIKE ?";
            $params[] = "%{$ticketFilter}%";
        }
        if (!empty($buyerFilter)) {
            $where[] = "b.name LIKE ?";
            $params[] = "%{$buyerFilter}%";
        }
        if (!empty($cpfFilter)) {
            $where[] = "b.cpf LIKE ?";
            $params[] = "%{$cpfFilter}%";
        }
        if (!empty($dateFrom)) {
            $where[] = "DATE(tal.created_at) >= ?";
            $params[] = $dateFrom;
        }
        if (!empty($dateTo)) {
            $where[] = "DATE(tal.created_at) <= ?";
            $params[] = $dateTo;
        }
        if (!empty($ipFilter)) {
            $where[] = "tal.ip_address LIKE ?";
            $params[] = "%{$ipFilter}%";
        }
        if (!empty($resultFilter)) {
            $where[] = "tal.result = ?";
            $params[] = $resultFilter;
        }

        $whereClause = implode(" AND ", $where);

        $page = max(1, (int)($_GET['page'] ?? 1));
        $perPage = 50;
        $offset = ($page - 1) * $perPage;

        $stmtCount = $pdo->prepare("
            SELECT COUNT(*)
            FROM ticket_access_logs tal
            LEFT JOIN tickets t ON t.id = tal.ticket_id
            LEFT JOIN buyers b ON b.id = tal.buyer_id
            WHERE {$whereClause}
        ");
        $stmtCount->execute($params);
        $totalItems = (int)$stmtCount->fetchColumn();
        $totalPages = ceil($totalItems / $perPage);

        $stmtLogs = $pdo->prepare("
            SELECT tal.*, t.ticket_number, b.name as buyer_name, b.cpf as buyer_cpf, b.phone as buyer_phone
            FROM ticket_access_logs tal
            LEFT JOIN tickets t ON t.id = tal.ticket_id
            LEFT JOIN buyers b ON b.id = tal.buyer_id
            WHERE {$whereClause}
            ORDER BY tal.id DESC
            LIMIT {$perPage} OFFSET {$offset}
        ");
        $stmtLogs->execute($params);
        $accessLogs = $stmtLogs->fetchAll(PDO::FETCH_ASSOC);

        View::render('audit/consultas', [
            'accessLogs' => $accessLogs,
            'page' => $page,
            'totalPages' => $totalPages,
            'totalItems' => $totalItems,
            'filters' => [
                'user' => $userFilter,
                'role' => $roleFilter,
                'ticket' => $ticketFilter,
                'buyer' => $buyerFilter,
                'cpf' => $cpfFilter,
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
                'ip' => $ipFilter,
                'result' => $resultFilter,
            ]
        ]);
    }
}
