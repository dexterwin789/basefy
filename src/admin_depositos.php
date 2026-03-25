<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';

function listarDepositos($conn, array $filtros = [], int $pagina = 1, int $porPagina = 20): array
{
    $pagina = max(1, $pagina);
    $offset = ($pagina - 1) * $porPagina;

    $q = '%' . trim((string)($filtros['q'] ?? '')) . '%';
    $status = trim((string)($filtros['status'] ?? ''));
    $de = trim((string)($filtros['de'] ?? ''));
    $ate = trim((string)($filtros['ate'] ?? ''));

    $where = ["pt.external_ref LIKE 'wallet_topup:%'", "(pt.provider_transaction_id LIKE ? OR pt.external_ref LIKE ? OR u.nome LIKE ? OR u.email LIKE ?)"];
    $types = 'ssss';
    $params = [$q, $q, $q, $q];

    if ($status !== '') {
        $where[] = 'pt.status = ?';
        $types .= 's';
        $params[] = strtoupper($status);
    }
    if ($de !== '') {
        $where[] = 'DATE(pt.created_at) >= ?';
        $types .= 's';
        $params[] = $de;
    }
    if ($ate !== '') {
        $where[] = 'DATE(pt.created_at) <= ?';
        $types .= 's';
        $params[] = $ate;
    }

    $whereSql = implode(' AND ', $where);

    $sqlCount = "SELECT COUNT(*) AS total
                 FROM payment_transactions pt
                 LEFT JOIN users u ON u.id = pt.user_id
                 WHERE $whereSql";
    $stCount = $conn->prepare($sqlCount);
    $stCount->bind_param($types, ...$params);
    $stCount->execute();
    $total = (int)($stCount->get_result()->fetch_assoc()['total'] ?? 0);

    $sqlList = "SELECT pt.id, pt.provider, pt.order_id, pt.user_id, pt.external_ref, pt.provider_transaction_id,
                       pt.status, pt.payment_method, pt.amount_centavos, pt.net_centavos, pt.fees_centavos,
                       pt.invoice_url, pt.created_at, pt.paid_at,
                       u.nome AS user_nome, u.email AS user_email
                FROM payment_transactions pt
                LEFT JOIN users u ON u.id = pt.user_id
                WHERE $whereSql
                ORDER BY pt.id DESC
                LIMIT ?, ?";

    $typesList = $types . 'ii';
    $paramsList = [...$params, $offset, $porPagina];
    $stList = $conn->prepare($sqlList);
    $stList->bind_param($typesList, ...$paramsList);
    $stList->execute();
    $itens = $stList->get_result()->fetch_all(MYSQLI_ASSOC);

    return [
        'itens' => $itens,
        'total' => $total,
        'pagina' => $pagina,
        'por_pagina' => $porPagina,
        'total_paginas' => max(1, (int)ceil($total / $porPagina)),
    ];
}

function obterDepositoPorId($conn, int $id): ?array
{
    if ($id <= 0) {
        return null;
    }

    $sql = "SELECT pt.*, u.nome AS user_nome, u.email AS user_email
            FROM payment_transactions pt
            LEFT JOIN users u ON u.id = pt.user_id
            WHERE pt.id = ?
            LIMIT 1";
    $st = $conn->prepare($sql);
    $st->bind_param('i', $id);
    $st->execute();
    $row = $st->get_result()->fetch_assoc();

    return $row ?: null;
}

function listarWebhooksRelacionadosAoDeposito($conn, array $deposito): array
{
    $txId = trim((string)($deposito['provider_transaction_id'] ?? ''));
    $externalRef = trim((string)($deposito['external_ref'] ?? ''));

    if ($txId === '' && $externalRef === '') {
        return [];
    }

    $where = [];
    $types = '';
    $params = [];

    if ($txId !== '') {
        $where[] = 'payload LIKE ?';
        $types .= 's';
        $params[] = '%"transactionId":"' . $txId . '"%';
    }

    if ($externalRef !== '') {
        $where[] = 'payload LIKE ?';
        $types .= 's';
        $params[] = '%"externalReference":"' . $externalRef . '"%';
    }

    $sql = 'SELECT id, provider, event_name, status, received_at, processed_at FROM webhook_events WHERE ' . implode(' OR ', $where) . ' ORDER BY id DESC LIMIT 50';
    $st = $conn->prepare($sql);
    $st->bind_param($types, ...$params);
    $st->execute();

    return $st->get_result()->fetch_all(MYSQLI_ASSOC);
}
