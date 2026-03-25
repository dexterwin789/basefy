<?php
// filepath: c:\xampp\htdocs\mercado_admin\public\vendedor\api_venda_detalhe_analise.php
require_once __DIR__ . '/../../src/auth.php';
require_once __DIR__ . '/../../src/db.php';
require_once __DIR__ . '/../../src/upload_paths.php';
require_once __DIR__ . '/../../src/storefront.php';

header('Content-Type: application/json; charset=UTF-8');
exigirVendedor();

$conn = (new Database())->connect();

// Ensure delivery columns exist
_sfEnsureDeliveryColumns($conn);

$uid = (int)($_SESSION['user_id'] ?? 0);
$orderId = (int)($_GET['order_id'] ?? 0);

if ($orderId <= 0) {
    echo json_encode(['ok' => false, 'msg' => 'Pedido inválido']); exit;
}

$st = $conn->prepare("
SELECT
  oi.order_id,
  o.user_id AS comprador_id,
  u.nome AS comprador_nome,
  u.email AS comprador_email,
  o.criado_em,
  o.status AS status_pedido,
  COALESCE(SUM(oi.subtotal),0) AS total_venda
FROM order_items oi
INNER JOIN orders o ON o.id = oi.order_id
LEFT JOIN users u ON u.id = o.user_id
WHERE oi.order_id = ?
  AND oi.vendedor_id = ?
  AND oi.moderation_status = 'pendente'
GROUP BY oi.order_id, o.user_id, u.nome, u.email, o.criado_em, o.status
LIMIT 1
");
$st->bind_param('ii', $orderId, $uid);
$st->execute();
$pedido = $st->get_result()->fetch_assoc();
$st->close();

if (!$pedido) {
    echo json_encode(['ok' => false, 'msg' => 'Venda não encontrada']); exit;
}

$it = $conn->prepare("
SELECT
  oi.id AS item_id,
  oi.product_id,
  COALESCE(p.nome, ('Produto #' || oi.product_id::text)) AS produto_nome,
  COALESCE(p.descricao, '') AS produto_descricao,
  COALESCE(p.imagem, '') AS produto_imagem,
  oi.quantidade,
  oi.preco_unit,
  oi.subtotal,
  oi.delivery_content,
  oi.delivered_at
FROM order_items oi
LEFT JOIN products p ON p.id = oi.product_id
WHERE oi.order_id = ?
  AND oi.vendedor_id = ?
  AND oi.moderation_status = 'pendente'
ORDER BY oi.id DESC
");
$it->bind_param('ii', $orderId, $uid);
$it->execute();
$itens = $it->get_result()->fetch_all(MYSQLI_ASSOC);
$it->close();

foreach ($itens as &$item) {
  $img = str_replace('\\', '/', (string)($item['produto_imagem'] ?? ''));
  $item['produto_imagem_url'] = uploadsPublicUrl($img, '');
}
unset($item);

echo json_encode(['ok' => true, 'pedido' => $pedido, 'itens' => $itens], JSON_UNESCAPED_UNICODE);