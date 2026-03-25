<?php
// filepath: c:\xampp\htdocs\mercado_admin\src\admin_solicitacoes.php
declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/storefront.php';
require_once __DIR__ . '/notifications.php';

function listarSolicitacoesVendedor($conn, array|string $f = [], int $pagina = 1, int $pp = 10): array
{
    if (!is_array($f)) {
        $f = ['q' => (string)$f];
    }

    $pagina = max(1, $pagina);
    $offset = ($pagina - 1) * $pp;

    $q = '%' . trim((string)($f['q'] ?? '')) . '%';
    $status = (string)($f['status'] ?? 'pendente');
    $de = (string)($f['de'] ?? '');
    $ate = (string)($f['ate'] ?? '');

    $where = ["(u.nome LIKE ? OR u.email LIKE ?)"];
    $types = "ss";
    $params = [$q, $q];

    if ($status !== '') { $where[] = "sr.status = ?"; $types .= "s"; $params[] = $status; }
    if ($de !== '') { $where[] = "DATE(sr.criado_em) >= ?"; $types .= "s"; $params[] = $de; }
    if ($ate !== '') { $where[] = "DATE(sr.criado_em) <= ?"; $types .= "s"; $params[] = $ate; }

    $whereSql = implode(' AND ', $where);

    $count = "SELECT COUNT(*) total FROM seller_requests sr INNER JOIN users u ON u.id=sr.user_id WHERE $whereSql";
    $st = $conn->prepare($count); $st->execute($params);
    $total = (int)($st->get_result()->fetch_assoc()['total'] ?? 0);

        $list = "SELECT sr.id solicitacao_id, sr.user_id, sr.status, sr.criado_em, sr.atualizado_em, sr.motivo_recusa,
                  u.nome, u.email,
                  sp.nome_loja, sp.documento, sp.telefone, sp.chave_pix, sp.bio
             FROM seller_requests sr
             INNER JOIN users u ON u.id=sr.user_id
              LEFT JOIN seller_profiles sp ON sp.user_id=sr.user_id
             WHERE $whereSql
             ORDER BY sr.id DESC
             LIMIT ?, ?";
    $params2 = [...$params, $offset, $pp];
    $st2 = $conn->prepare($list); $st2->execute($params2);
    $itens = $st2->get_result()->fetch_all(MYSQLI_ASSOC);

    return ['itens'=>$itens,'total'=>$total,'pagina'=>$pagina,'total_paginas'=>max(1,(int)ceil($total/$pp))];
}

function decidirSolicitacaoVendedor($conn, int $id, string $acao, string $motivo = ''): array
{
    if ($id <= 0 || !in_array($acao, ['aprovar', 'recusar'], true)) return [false, 'Parâmetros inválidos.'];
    if ($acao === 'recusar' && trim($motivo) === '') return [false, 'Informe o motivo.'];

    $conn->begin_transaction();
    try {
        $s = $conn->prepare("SELECT user_id, status FROM seller_requests WHERE id=? FOR UPDATE");
        $s->bind_param('i', $id); $s->execute();
        $r = $s->get_result()->fetch_assoc();
        if (!$r) { $conn->rollback(); return [false, 'Solicitação não encontrada.']; }
        if (!in_array($r['status'], ['pendente','aberto'], true)) { $conn->rollback(); return [false, 'Já processada.']; }

        $uid = (int)$r['user_id'];
        if ($acao === 'aprovar') {
            $st = $conn->prepare("UPDATE seller_requests SET status='aprovada', motivo_recusa=NULL WHERE id=?");
            $st->bind_param('i', $id); $st->execute();
            $u = $conn->prepare("UPDATE users SET role='vendedor', is_vendedor=1, status_vendedor='aprovado' WHERE id=?");
            $u->bind_param('i', $uid); $u->execute();

            // Generate vendor slug from nome_loja if not set
            $spQ = $conn->prepare("SELECT sp.nome_loja, u.nome, u.slug FROM users u LEFT JOIN seller_profiles sp ON sp.user_id = u.id WHERE u.id = ? LIMIT 1");
            if ($spQ) {
                $spQ->bind_param('i', $uid); $spQ->execute();
                $spRow = $spQ->get_result()->fetch_assoc();
                $spQ->close();
                if ($spRow && (trim((string)($spRow['slug'] ?? '')) === '')) {
                    $label = trim((string)($spRow['nome_loja'] ?? '')) ?: (string)$spRow['nome'];
                    $vendorSlug = sfCreateUniqueVendorSlug($conn, $label, $uid);
                    $upSlug = $conn->prepare("UPDATE users SET slug = ? WHERE id = ?");
                    if ($upSlug) { $upSlug->bind_param('si', $vendorSlug, $uid); $upSlug->execute(); $upSlug->close(); }
                }
            }

            $conn->commit();

            // Notify user about seller approval
            try {
                require_once __DIR__ . '/notifications.php';
                notificationsCreate($conn, $uid, 'anuncio', 'Solicitação aprovada!', 'Parabéns! Sua solicitação para se tornar vendedor foi aprovada. Configure sua loja agora.', '/vendedor/dashboard');
            } catch (\Throwable $e) {}

            return [true, 'Aprovada.'];
        }

        $m = trim($motivo);
        $st = $conn->prepare("UPDATE seller_requests SET status='rejeitada', motivo_recusa=? WHERE id=?");
        $st->bind_param('si', $m, $id); $st->execute();
        $u = $conn->prepare("UPDATE users SET is_vendedor=0, status_vendedor='rejeitado' WHERE id=?");
        $u->bind_param('i', $uid); $u->execute();
        $conn->commit();

        // Notify user about seller rejection
        try {
            require_once __DIR__ . '/notifications.php';
            notificationsCreate($conn, $uid, 'anuncio', 'Solicitação recusada', 'Sua solicitação para se tornar vendedor foi recusada. Motivo: ' . $m, '/vendedor/aprovacao');
        } catch (\Throwable $e2) {}

        return [true, 'Recusada.'];
    } catch (Throwable $e) {
        $conn->rollback();
        return [false, 'Erro ao processar.'];
    }
}

function obterSolicitacaoVendedorPorId($conn, int $id): ?array
{
    if ($id <= 0) {
        return null;
    }

    $sql = "SELECT sr.id AS solicitacao_id, sr.user_id, sr.status, sr.criado_em, sr.atualizado_em, sr.motivo_recusa,
                   u.nome, u.email, u.role, u.status_vendedor,
                   sp.nome_loja, sp.documento, sp.telefone, sp.chave_pix, sp.bio
            FROM seller_requests sr
            INNER JOIN users u ON u.id = sr.user_id
            LEFT JOIN seller_profiles sp ON sp.user_id = sr.user_id
            WHERE sr.id = ?
            LIMIT 1";

    $st = $conn->prepare($sql);
    if (!$st) {
        return null;
    }
    $st->bind_param('i', $id);
    $st->execute();
    $row = $st->get_result()->fetch_assoc();
    return $row ?: null;
}