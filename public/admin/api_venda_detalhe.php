<?php
declare(strict_types=1);
// Admin API: Sale detail
require_once __DIR__ . '/../../src/auth.php';
require_once __DIR__ . '/../../src/db.php';
require_once __DIR__ . '/../../src/upload_paths.php';
require_once __DIR__ . '/../../src/storefront.php';

header('Content-Type: application/json; charset=UTF-8');
exigirAdmin();

$conn = (new Database())->connect();
_sfEnsureDeliveryColumns($conn);
_sfEnsureOrderWalletColumns($conn);

$vendaId = (int)($_GET['id'] ?? 0);
if ($vendaId <= 0) {
    echo json_encode(['ok' => false, 'msg' => 'ID inválido']); exit;
}

$st = $conn->prepare("
SELECT
  oi.id AS venda_id,
  oi.order_id,
  oi.product_id,
  oi.vendedor_id,
  oi.quantidade,
  oi.preco_unit,
  oi.subtotal,
  oi.moderation_status,
  oi.moderation_motivo,
  oi.moderation_at,
  oi.delivery_content,
  oi.delivered_at,
  oi.escrow_fee_percent,
  oi.escrow_fee_amount,
  oi.escrow_net_amount,
  o.status AS status_pedido,
  o.criado_em,
  o.total AS total_pedido,
  o.gross_total,
  o.wallet_used,
  b.id AS comprador_id, b.nome AS comprador_nome, b.email AS comprador_email,
  s.id AS vendedor_id2, s.nome AS vendedor_nome, s.email AS vendedor_email,
  p.nome AS produto_nome, p.descricao AS produto_descricao, p.imagem AS produto_imagem
FROM order_items oi
INNER JOIN orders o ON o.id = oi.order_id
INNER JOIN users b ON b.id = o.user_id
INNER JOIN users s ON s.id = oi.vendedor_id
INNER JOIN products p ON p.id = oi.product_id
WHERE oi.id = ?
LIMIT 1
");
$st->bind_param('i', $vendaId);
$st->execute();
$row = $st->get_result()->fetch_assoc();
$st->close();

if (!$row) {
    echo json_encode(['ok' => false, 'msg' => 'Venda não encontrada']); exit;
}

$img = str_replace('\\', '/', (string)($row['produto_imagem'] ?? ''));
$row['produto_imagem_url'] = uploadsPublicUrl($img, '');

echo json_encode(['ok' => true, 'venda' => $row], JSON_UNESCAPED_UNICODE);
