<?php
// filepath: c:\xampp\htdocs\mercado_admin\src\admin_pedidos.php
declare(strict_types=1);

require_once __DIR__ . '/db.php';

function listarPedidos($conn, array $f = [], int $pagina = 1, int $pp = 20): array
{
    $pagina = max(1, $pagina);
    $offset = ($pagina - 1) * $pp;

    $where = ["(u.nome LIKE ? OR u.email LIKE ?)"];
    $types = "ss";
    $params = ['%' . trim((string)($f['q'] ?? '')) . '%', '%' . trim((string)($f['q'] ?? '')) . '%'];

    if (!empty($f['status'])) { $where[]="o.status=?"; $types.="s"; $params[]=$f['status']; }
    if (!empty($f['de'])) { $where[]="DATE(o.criado_em)>=?"; $types.="s"; $params[]=$f['de']; }
    if (!empty($f['ate'])) { $where[]="DATE(o.criado_em)<=?"; $types.="s"; $params[]=$f['ate']; }

    $sql = "SELECT o.id, o.status, o.total, o.criado_em, u.nome user_nome, u.email user_email
            FROM orders o
            INNER JOIN users u ON u.id=o.user_id
            WHERE " . implode(' AND ', $where) . "
            ORDER BY o.id DESC
            LIMIT ?, ?";
    $types .= "ii";
    $params[] = $offset; $params[] = $pp;

    $st = $conn->prepare($sql);
    $st->bind_param($types, ...$params);
    $st->execute();

    return ['itens' => $st->get_result()->fetch_all(MYSQLI_ASSOC)];
}