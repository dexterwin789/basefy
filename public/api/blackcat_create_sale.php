<?php
declare(strict_types=1);

require_once __DIR__ . '/../../src/auth.php';
require_once __DIR__ . '/../../src/db.php';
require_once __DIR__ . '/../../src/blackcat_api.php';
require_once __DIR__ . '/../../src/wallet_escrow.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'msg' => 'Método não permitido']);
    exit;
}

if (!usuarioLogado()) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'msg' => 'Não autenticado']);
    exit;
}

$conn = (new Database())->connect();
$buyerId = (int)($_SESSION['user_id'] ?? 0);
$orderId = (int)($_POST['order_id'] ?? 0);

if ($orderId <= 0 || $buyerId <= 0) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'msg' => 'Parâmetros inválidos']);
    exit;
}

$st = $conn->prepare('SELECT id, user_id, status, total FROM orders WHERE id = ? AND user_id = ? LIMIT 1');
$st->bind_param('ii', $orderId, $buyerId);
$st->execute();
$order = $st->get_result()->fetch_assoc();

if (!$order) {
    http_response_code(404);
    echo json_encode(['ok' => false, 'msg' => 'Pedido não encontrado']);
    exit;
}

if (in_array((string)$order['status'], ['pago', 'enviado', 'entregue'], true)) {
    echo json_encode(['ok' => true, 'msg' => 'Pedido já pago', 'status' => $order['status']]);
    exit;
}

$stBuyer = $conn->prepare('SELECT nome, email FROM users WHERE id = ? LIMIT 1');
$stBuyer->bind_param('i', $buyerId);
$stBuyer->execute();
$buyer = $stBuyer->get_result()->fetch_assoc() ?: [];

$itemsQ = $conn->prepare('SELECT oi.quantidade, oi.preco_unit, p.nome FROM order_items oi INNER JOIN products p ON p.id = oi.product_id WHERE oi.order_id = ?');
$itemsQ->bind_param('i', $orderId);
$itemsQ->execute();
$itemRows = $itemsQ->get_result()->fetch_all(MYSQLI_ASSOC);

if (!$itemRows) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'msg' => 'Pedido sem itens']);
    exit;
}

$amountCentavos = (int)round(((float)$order['total']) * 100);
$payloadItems = [];
foreach ($itemRows as $item) {
    $payloadItems[] = [
        'title' => (string)$item['nome'],
        'unitPrice' => (int)round(((float)$item['preco_unit']) * 100),
        'quantity' => (int)$item['quantidade'],
        'tangible' => false,
    ];
}

if ($amountCentavos <= 0) {
    echo json_encode(['ok' => true, 'msg' => 'Pedido já quitado', 'status' => (string)$order['status']]);
    exit;
}

$sumItemsCentavos = 0;
foreach ($payloadItems as $payloadItem) {
    $sumItemsCentavos += ((int)$payloadItem['unitPrice']) * ((int)$payloadItem['quantity']);
}

if ($sumItemsCentavos !== $amountCentavos) {
    $payloadItems = [[
        'title' => 'Pedido #' . $orderId,
        'unitPrice' => $amountCentavos,
        'quantity' => 1,
        'tangible' => false,
    ]];
}

$externalRef = 'order:' . $orderId;

$payload = [
    'amount' => $amountCentavos,
    'currency' => 'BRL',
    'paymentMethod' => 'pix',
    'items' => $payloadItems,
    'customer' => [
        'name' => (string)($buyer['nome'] ?? 'Cliente'),
        'email' => (string)($buyer['email'] ?? 'cliente@local'),
        'phone' => '11999999999',
        'document' => [
            'number' => '00000000000',
            'type' => 'cpf',
        ],
    ],
    'pix' => ['expiresInDays' => 1],
    'postbackUrl' => APP_URL . '/webhooks/blackcat',
    'externalRef' => $externalRef,
    'metadata' => 'Pedido interno #' . $orderId,
];

[$ok, $resp] = blackcatCreatePixSale($payload);
if (!$ok) {
    http_response_code(502);
    echo json_encode(['ok' => false, 'msg' => (string)($resp['message'] ?? 'Falha ao criar cobrança'), 'error' => $resp]);
    exit;
}

$data = $resp['data'] ?? [];
$providerTransactionId = (string)($data['transactionId'] ?? '');
$status = (string)($data['status'] ?? 'PENDING');
$net = isset($data['netAmount']) ? (int)$data['netAmount'] : null;
$fees = isset($data['fees']) ? (int)$data['fees'] : null;
$invoiceUrl = (string)($data['invoiceUrl'] ?? '');

$ins = $conn->prepare("INSERT INTO payment_transactions
    (provider, order_id, user_id, external_ref, provider_transaction_id, status, payment_method, amount_centavos, net_centavos, fees_centavos, invoice_url, raw_response)
    VALUES ('blackcat', ?, ?, ?, ?, ?, 'pix', ?, ?, ?, ?, ?)
    ON DUPLICATE KEY UPDATE
      status = VALUES(status),
      net_centavos = VALUES(net_centavos),
      fees_centavos = VALUES(fees_centavos),
      invoice_url = VALUES(invoice_url),
      raw_response = VALUES(raw_response),
      updated_at = CURRENT_TIMESTAMP");
$raw = json_encode($resp, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
$ins->bind_param('iisssiiiss', $orderId, $buyerId, $externalRef, $providerTransactionId, $status, $amountCentavos, $net, $fees, $invoiceUrl, $raw);
$ins->execute();

echo json_encode([
    'ok' => true,
    'transactionId' => $providerTransactionId,
    'status' => $status,
    'invoiceUrl' => $invoiceUrl,
    'paymentData' => $data['paymentData'] ?? new stdClass(),
]);
