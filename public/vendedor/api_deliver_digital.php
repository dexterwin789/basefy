<?php
declare(strict_types=1);
/**
 * Vendor API: Submit digital delivery content (link / text) for an order item.
 *
 * POST  { order_id: int, item_id: int, delivery_content: string }
 * Returns  { ok: bool, msg: string }
 */

require_once __DIR__ . '/../../src/auth.php';
require_once __DIR__ . '/../../src/db.php';
require_once __DIR__ . '/../../src/storefront.php';
require_once __DIR__ . '/../../src/wallet_escrow.php';

header('Content-Type: application/json; charset=UTF-8');
exigirVendedor();

$conn = (new Database())->connect();

// Ensure columns exist
_sfEnsureDeliveryColumns($conn);

$uid = (int)($_SESSION['user_id'] ?? 0);

$input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
$orderId  = (int)($input['order_id'] ?? 0);
$itemId   = (int)($input['item_id'] ?? 0);
$content  = trim((string)($input['delivery_content'] ?? ''));

if ($orderId <= 0 || $itemId <= 0) {
    echo json_encode(['ok' => false, 'msg' => 'Pedido ou item inválido.']);
    exit;
}

if ($content === '') {
    echo json_encode(['ok' => false, 'msg' => 'Conteúdo de entrega não pode ser vazio.']);
    exit;
}

// Verify the item belongs to this vendor and this order, and order is paid
$st = $conn->prepare("
    SELECT oi.id, oi.order_id, oi.delivery_content, o.status AS order_status
    FROM order_items oi
    INNER JOIN orders o ON o.id = oi.order_id
    WHERE oi.id = ?
      AND oi.order_id = ?
      AND oi.vendedor_id = ?
    LIMIT 1
");
$st->bind_param('iii', $itemId, $orderId, $uid);
$st->execute();
$item = $st->get_result()->fetch_assoc();
$st->close();

if (!$item) {
    echo json_encode(['ok' => false, 'msg' => 'Item não encontrado ou não pertence a você.']);
    exit;
}

$orderStatus = strtolower(trim((string)($item['order_status'] ?? '')));
if (!in_array($orderStatus, ['pago', 'entregue'], true)) {
    echo json_encode(['ok' => false, 'msg' => 'O pedido ainda não foi pago. Aguarde a confirmação do pagamento.']);
    exit;
}

// Save delivery content
$up = $conn->prepare("UPDATE order_items SET delivery_content = ?, delivered_at = NOW() WHERE id = ? AND vendedor_id = ?");
$up->bind_param('sii', $content, $itemId, $uid);
$up->execute();
$up->close();

// Notify buyer about digital delivery
try {
    require_once __DIR__ . '/../../src/notifications.php';
    $stBuyer = $conn->prepare("SELECT user_id FROM orders WHERE id = ? LIMIT 1");
    $stBuyer->bind_param('i', $orderId);
    $stBuyer->execute();
    $buyerRow = $stBuyer->get_result()->fetch_assoc();
    $stBuyer->close();
    if ($buyerRow && (int)$buyerRow['user_id'] > 0) {
        notificationsCreate($conn, (int)$buyerRow['user_id'], 'venda', 'Entrega digital disponível!', 'O vendedor enviou o conteúdo do seu pedido #' . $orderId . '. Acesse para verificar.', '/pedido_detalhes?id=' . $orderId);
    }
} catch (\Throwable $e) { error_log('[DigitalDelivery] Notification error: ' . $e->getMessage()); }

// Reset auto_release_at so the buyer has time to verify the delivered content
try {
    $autoReleaseDays = (int)escrowSettingGet($conn, 'wallet.auto_release_days', '7');
    if ($autoReleaseDays < 1) $autoReleaseDays = 7;
    $intervalStr = $autoReleaseDays . ' days';
    $upTimer = $conn->prepare("UPDATE order_items SET auto_release_at = NOW() + INTERVAL '$intervalStr' WHERE id = ? AND vendedor_id = ?");
    if ($upTimer) {
        $upTimer->bind_param('ii', $itemId, $uid);
        $upTimer->execute();
        $upTimer->close();
    }
} catch (\Throwable $e) {
    // Non-critical: auto-release timer reset failed, delivery was still saved
}

echo json_encode(['ok' => true, 'msg' => 'Entrega digital enviada com sucesso! O comprador pode agora acessar o conteúdo.'], JSON_UNESCAPED_UNICODE);
