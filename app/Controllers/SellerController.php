<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Database;
use App\Core\Response;
use App\Core\View;
use App\Services\AuditService;

class SellerController
{
    public function index(): void
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->query("
            SELECT s.*,
                   COALESCE(SUM(sa.quantity), 0) as total_qty,
                   COALESCE(SUM(sa.amount), 0.00) as total_sales,
                   COUNT(DISTINCT sa.operation_day_id) as days_active
            FROM sellers s
            LEFT JOIN sales sa ON sa.seller_id = s.id AND sa.cancelled_at IS NULL
            GROUP BY s.id
            ORDER BY s.active DESC, s.name ASC
        ");
        $sellers = $stmt->fetchAll();

        View::render('sellers/index', [
            'title' => 'Vendedores(as) — Show de Prêmios',
            'sellers' => $sellers,
        ]);
    }

    public function create(): void
    {
        $name = trim($_POST['name'] ?? '');
        $nickname = trim($_POST['nickname'] ?? '');
        $notes = trim($_POST['notes'] ?? '');

        $redirect = trim($_POST['redirect'] ?? '');
        $fallbackUrl = !empty($redirect) ? $redirect : '/configuracoes?tab=vendedores';

        if (empty($name)) {
            Response::redirect($fallbackUrl, null, 'O nome do vendedor(a) é obrigatório.');
        }

        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("
            INSERT INTO sellers (name, nickname, active, started_at, notes, created_at, updated_at)
            VALUES (?, ?, 1, CURRENT_DATE, ?, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
        ");
        $stmt->execute([$name, $nickname ?: null, $notes ?: null]);
        $sellerId = (int)$pdo->lastInsertId();

        AuditService::log('SELLER_CREATE', 'sellers', $sellerId, null, ['name' => $name]);

        Response::redirect($fallbackUrl, "Vendedor(a) '{$name}' cadastrado(a) com sucesso!");
    }

    public function toggleStatus(): void
    {
        if (!Auth::isAdmin()) {
            Response::redirect('/configuracoes?tab=vendedores', null, 'Permissão negada: Somente administradores podem inativar ou reativar vendedores.');
        }

        $sellerId = (int)($_POST['seller_id'] ?? 0);

        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("SELECT * FROM sellers WHERE id = ?");
        $stmt->execute([$sellerId]);
        $seller = $stmt->fetch();

        if (!$seller) {
            Response::redirect('/configuracoes?tab=vendedores', null, 'Vendedor(a) não encontrado(a).');
        }

        $newActive = $seller['active'] ? 0 : 1;
        $endedAt = $newActive === 0 ? date('Y-m-d') : null;

        $stmtUp = $pdo->prepare("UPDATE sellers SET active = ?, ended_at = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
        $stmtUp->execute([$newActive, $endedAt, $sellerId]);

        $action = $newActive ? 'SELLER_ACTIVATE' : 'SELLER_INACTIVATE';
        AuditService::log($action, 'sellers', $sellerId, ['active' => $seller['active']], ['active' => $newActive]);

        $msg = $newActive ? "Vendedor(a) reativado(a) com sucesso!" : "Vendedor(a) inativado(a) com sucesso (histórico preservado).";
        Response::redirect('/configuracoes?tab=vendedores', $msg);
    }

    public function update(): void
    {
        if (!Auth::isAdmin()) {
            Response::redirect('/configuracoes?tab=vendedores', null, 'Permissão negada: Somente administradores podem alterar o cadastro de vendedores.');
        }

        $sellerId = (int)($_POST['seller_id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $nickname = trim($_POST['nickname'] ?? '');
        $notes = trim($_POST['notes'] ?? '');

        if (empty($name)) {
            Response::redirect('/configuracoes?tab=vendedores', null, 'O nome do vendedor(a) é obrigatório.');
        }

        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("SELECT * FROM sellers WHERE id = ?");
        $stmt->execute([$sellerId]);
        $seller = $stmt->fetch();

        if (!$seller) {
            Response::redirect('/configuracoes?tab=vendedores', null, 'Vendedor(a) não encontrado(a).');
        }

        $stmtUp = $pdo->prepare("
            UPDATE sellers 
            SET name = ?, nickname = ?, notes = ?, updated_at = CURRENT_TIMESTAMP 
            WHERE id = ?
        ");
        $stmtUp->execute([$name, $nickname ?: null, $notes ?: null, $sellerId]);

        AuditService::log('SELLER_UPDATE', 'sellers', $sellerId, $seller, [
            'name' => $name,
            'nickname' => $nickname,
            'notes' => $notes,
        ]);

        Response::redirect('/configuracoes?tab=vendedores', "Dados do vendedor(a) '{$name}' atualizados com sucesso!");
    }
}
