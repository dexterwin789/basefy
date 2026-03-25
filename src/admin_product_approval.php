<?php
// filepath: c:\xampp\htdocs\mercado_admin\src\admin_product_approval.php
declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/notifications.php';
require_once __DIR__ . '/push_debug.php';

/**
 * Ensure status_aprovacao and motivo_recusa columns exist in products table.
 */
function _prodApprovalEnsureCols($conn): void
{
    static $done = false;
    if ($done) return;
    $done = true;
    try { $conn->query("ALTER TABLE products ADD COLUMN IF NOT EXISTS status_aprovacao VARCHAR(20) NOT NULL DEFAULT 'pendente'"); } catch (\Throwable $e) {}
    try { $conn->query("ALTER TABLE products ADD COLUMN IF NOT EXISTS motivo_recusa TEXT DEFAULT NULL"); } catch (\Throwable $e) {}
}

/**
 * List product approval requests with filters and pagination.
 */
function listarSolicitacoesProduto($conn, array $f = [], int $pagina = 1, int $pp = 10): array
{
    _prodApprovalEnsureCols($conn);

    $pagina = max(1, $pagina);
    $offset = ($pagina - 1) * $pp;

    $q = '%' . trim((string)($f['q'] ?? '')) . '%';
    $status = (string)($f['status'] ?? 'pendente');

    $where = ["(p.nome LIKE ? OR u.nome LIKE ?)"];
    $types = "ss";
    $params = [$q, $q];

    if ($status !== '') {
        $where[] = "COALESCE(p.status_aprovacao, 'aprovado') = ?";
        $types .= "s";
        $params[] = $status;
    }

    $whereSql = implode(' AND ', $where);

    // Detect vendor column
    $cols = [];
    $rs = $conn->query("SHOW COLUMNS FROM products");
    if ($rs) while ($r = $rs->fetch_assoc()) $cols[] = strtolower((string)$r['Field']);
    $vendorCol = in_array('vendedor_id', $cols) ? 'vendedor_id' : (in_array('user_id', $cols) ? 'user_id' : 'vendedor_id');

    // Count
    $countSql = "SELECT COUNT(*) total FROM products p LEFT JOIN users u ON u.id = p.{$vendorCol} WHERE $whereSql";
    $stC = $conn->prepare($countSql);
    $stC->execute($params);
    $total = (int)($stC->get_result()->fetch_assoc()['total'] ?? 0);
    $stC->close();

    // List
    $listSql = "SELECT p.id, p.nome, p.preco, p.imagem, p.criado_em,
                       p.variantes,
                       COALESCE(p.tipo, 'produto') AS tipo,
                       COALESCE(p.status_aprovacao, 'aprovado') AS status_aprovacao,
                       p.motivo_recusa,
                       u.id AS vendedor_id, u.nome AS vendedor_nome, u.email AS vendedor_email
                FROM products p
                LEFT JOIN users u ON u.id = p.{$vendorCol}
                WHERE $whereSql
                ORDER BY p.id DESC
                LIMIT ?, ?";
    $params2 = [...$params, $offset, $pp];
    $st = $conn->prepare($listSql);
    $st->execute($params2);
    $itens = $st->get_result()->fetch_all(MYSQLI_ASSOC);
    $st->close();

    return ['itens' => $itens, 'total' => $total, 'pagina' => $pagina, 'total_paginas' => max(1, (int)ceil($total / $pp))];
}

/**
 * Get a single product for approval detail view.
 */
function obterProdutoParaAprovacao($conn, int $productId): ?array
{
    _prodApprovalEnsureCols($conn);

    $cols = [];
    $rs = $conn->query("SHOW COLUMNS FROM products");
    if ($rs) while ($r = $rs->fetch_assoc()) $cols[] = strtolower((string)$r['Field']);
    $vendorCol = in_array('vendedor_id', $cols) ? 'vendedor_id' : (in_array('user_id', $cols) ? 'user_id' : 'vendedor_id');

    $sql = "SELECT p.*, u.nome AS vendedor_nome, u.email AS vendedor_email, u.id AS uid_vendedor
            FROM products p
            LEFT JOIN users u ON u.id = p.{$vendorCol}
            WHERE p.id = ?
            LIMIT 1";

    $st = $conn->prepare($sql);
    if (!$st) return null;
    $st->bind_param('i', $productId);
    $st->execute();
    $row = $st->get_result()->fetch_assoc();
    $st->close();
    return $row ?: null;
}

/**
 * Approve or reject a product.
 */
function decidirSolicitacaoProduto($conn, int $productId, string $acao, string $motivo = ''): array
{
    _prodApprovalEnsureCols($conn);

    if ($productId <= 0 || !in_array($acao, ['aprovar', 'recusar'], true)) {
        return [false, 'Parâmetros inválidos.'];
    }
    if ($acao === 'recusar' && trim($motivo) === '') {
        return [false, 'Informe o motivo da recusa.'];
    }

    $st = $conn->prepare("SELECT id, COALESCE(status_aprovacao, 'aprovado') AS status_aprovacao FROM products WHERE id = ? LIMIT 1");
    $st->bind_param('i', $productId);
    $st->execute();
    $prod = $st->get_result()->fetch_assoc();
    $st->close();

    if (!$prod) return [false, 'Produto não encontrado.'];
    if ($prod['status_aprovacao'] !== 'pendente') return [false, 'Este produto já foi processado.'];

    if ($acao === 'aprovar') {
        $up = $conn->prepare("UPDATE products SET status_aprovacao = 'aprovado', ativo = 1, motivo_recusa = NULL WHERE id = ?");
        $up->bind_param('i', $productId);
        $up->execute();
        $up->close();

        // Notify vendor (in-app + email)
        try {
            $cols = [];
            $rs = $conn->query("SHOW COLUMNS FROM products");
            if ($rs) while ($r = $rs->fetch_assoc()) $cols[] = strtolower((string)$r['Field']);
            $vendorCol = in_array('vendedor_id', $cols) ? 'vendedor_id' : 'user_id';
            $nameCol = in_array('nome', $cols) ? 'nome' : (in_array('titulo', $cols) ? 'titulo' : 'nome');
            $stV = $conn->prepare("SELECT {$vendorCol} AS vid, {$nameCol} AS pname FROM products WHERE id = ? LIMIT 1");
            $stV->bind_param('i', $productId);
            $stV->execute();
            $pRow = $stV->get_result()->fetch_assoc();
            $vid = (int)($pRow['vid'] ?? 0);
            $pname = (string)($pRow['pname'] ?? 'Produto');
            $stV->close();
            if ($vid > 0) {
                pushDebugLog('decidirSolicitacaoProduto: APPROVE — calling notificationsCreate', ['productId' => $productId, 'vendorId' => $vid, 'pname' => $pname]);
                require_once __DIR__ . '/notifications.php';
                notificationsCreate($conn, $vid, 'anuncio', 'Produto aprovado!', 'Seu produto foi aprovado e já está visível na loja.', '/vendedor/produtos', ['skip_email' => true]);
                pushDebugLog('decidirSolicitacaoProduto: APPROVE — notificationsCreate returned', ['productId' => $productId, 'vendorId' => $vid]);
                // Email notification
                try {
                    require_once __DIR__ . '/email.php';
                    if (smtpConfigured()) {
                        $vu = $conn->prepare("SELECT email, nome FROM users WHERE id = ? LIMIT 1");
                        $vu->bind_param('i', $vid);
                        $vu->execute();
                        $vUser = $vu->get_result()->fetch_assoc();
                        $vu->close();
                        if ($vUser && !empty($vUser['email'])) {
                            smtpSend($vUser['email'], 'Produto aprovado — ' . $pname, emailProdutoAprovado((string)($vUser['nome'] ?? 'Vendedor'), $pname));
                        }
                    }
                } catch (\Throwable $e) { error_log('[Approval] email error: ' . $e->getMessage()); }
            }
        } catch (\Throwable $e) { pushDebugLog('decidirSolicitacaoProduto: APPROVE notification EXCEPTION', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]); }

        return [true, 'Produto aprovado com sucesso.'];
    }

    // Reject
    $m = trim($motivo);
    $up = $conn->prepare("UPDATE products SET status_aprovacao = 'rejeitado', ativo = 0, motivo_recusa = ? WHERE id = ?");
    $up->bind_param('si', $m, $productId);
    $up->execute();
    $up->close();

    // Notify vendor (in-app + email)
    try {
        $cols = [];
        $rs = $conn->query("SHOW COLUMNS FROM products");
        if ($rs) while ($r = $rs->fetch_assoc()) $cols[] = strtolower((string)$r['Field']);
        $vendorCol = in_array('vendedor_id', $cols) ? 'vendedor_id' : 'user_id';
        $nameCol2 = in_array('nome', $cols) ? 'nome' : (in_array('titulo', $cols) ? 'titulo' : 'nome');
        $stV = $conn->prepare("SELECT {$vendorCol} AS vid, {$nameCol2} AS pname FROM products WHERE id = ? LIMIT 1");
        $stV->bind_param('i', $productId);
        $stV->execute();
        $pRow2 = $stV->get_result()->fetch_assoc();
        $vid = (int)($pRow2['vid'] ?? 0);
        $pname2 = (string)($pRow2['pname'] ?? 'Produto');
        $stV->close();
        if ($vid > 0) {
            pushDebugLog('decidirSolicitacaoProduto: REJECT — calling notificationsCreate', ['productId' => $productId, 'vendorId' => $vid, 'motivo' => $m]);
            require_once __DIR__ . '/notifications.php';
            notificationsCreate($conn, $vid, 'anuncio', 'Produto recusado', 'Seu produto foi recusado. Motivo: ' . $m, '/vendedor/produtos', ['skip_email' => true]);
            pushDebugLog('decidirSolicitacaoProduto: REJECT — notificationsCreate returned', ['productId' => $productId, 'vendorId' => $vid]);
            // Email notification
            try {
                require_once __DIR__ . '/email.php';
                if (smtpConfigured()) {
                    $vu2 = $conn->prepare("SELECT email, nome FROM users WHERE id = ? LIMIT 1");
                    $vu2->bind_param('i', $vid);
                    $vu2->execute();
                    $vUser2 = $vu2->get_result()->fetch_assoc();
                    $vu2->close();
                    if ($vUser2 && !empty($vUser2['email'])) {
                        smtpSend($vUser2['email'], 'Produto precisa de revisão — ' . $pname2, emailProdutoRevisao((string)($vUser2['nome'] ?? 'Vendedor'), $pname2, $m));
                    }
                }
            } catch (\Throwable $e) { error_log('[Rejection] email error: ' . $e->getMessage()); }
        }
    } catch (\Throwable $e) { pushDebugLog('decidirSolicitacaoProduto: REJECT notification EXCEPTION', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]); }

    return [true, 'Produto recusado.'];
}

/**
 * Count pending product approvals.
 */
function contarProdutosPendentes($conn): int
{
    _prodApprovalEnsureCols($conn);
    $rs = $conn->query("SELECT COUNT(*) total FROM products WHERE status_aprovacao = 'pendente'");
    if (!$rs) return 0;
    return (int)($rs->fetch_assoc()['total'] ?? 0);
}
