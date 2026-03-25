<?php
declare(strict_types=1);

require_once __DIR__ . '/../../src/auth.php';
require_once __DIR__ . '/../../src/db.php';
require_once __DIR__ . '/../../src/wallet_portal.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'msg' => 'Método não permitido']);
    exit;
}

if (!usuarioLogado()) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'msg' => 'Não autenticado']);
    exit;
}

$txId = (int)($_GET['tx_id'] ?? 0);
$userId = (int)($_SESSION['user_id'] ?? 0);

if ($txId <= 0 || $userId <= 0) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'msg' => 'Parâmetros inválidos']);
    exit;
}

$conn = (new Database())->connect();
$tx = walletObterRecargaPorId($conn, $userId, $txId);
if (!$tx) {
    http_response_code(404);
    echo json_encode(['ok' => false, 'msg' => 'Recarga não encontrada']);
    exit;
}

[$ok, $msg, $status] = walletAtualizarStatusRecarga($conn, $userId, $txId);
$fresh = walletObterRecargaPorId($conn, $userId, $txId);
$saldo = walletSaldo($conn, $userId);

if (!$ok) {
    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'msg' => $msg,
        'status' => strtoupper((string)($fresh['status'] ?? $status ?? 'UNKNOWN')),
    ]);
    exit;
}

echo json_encode([
    'ok' => true,
    'msg' => $msg,
    'status' => strtoupper((string)($fresh['status'] ?? $status ?? 'UNKNOWN')),
    'paid' => strtoupper((string)($fresh['status'] ?? $status ?? 'UNKNOWN')) === 'PAID',
    'saldo' => $saldo,
]);
