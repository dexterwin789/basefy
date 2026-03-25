<?php
declare(strict_types=1);

require_once __DIR__ . '/../../src/db.php';
require_once __DIR__ . '/../../src/wallet_escrow.php';
require_once __DIR__ . '/../../src/wallet_portal.php';

header('Content-Type: application/json; charset=utf-8');

$payloadRaw = file_get_contents('php://input') ?: '{}';
$data = json_decode($payloadRaw, true);
if (!is_array($data)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'msg' => 'Payload inválido']);
    exit;
}

$event = (string)($data['event'] ?? '');
$transactionId = (string)($data['transactionId'] ?? '');
$externalReference = (string)($data['externalReference'] ?? '');
$status = (string)($data['status'] ?? '');

if ($event === '') {
    http_response_code(400);
    echo json_encode(['ok' => false, 'msg' => 'Evento ausente']);
    exit;
}

$db = new Database();
$conn = $db->connect();

$idempotencyKey = sha1($event . '|' . $transactionId . '|' . $status . '|' . ($data['timestamp'] ?? ''));

try {
    $ins = $conn->prepare("INSERT INTO webhook_events (provider, event_name, idempotency_key, payload, status)
                           VALUES ('blackcat', ?, ?, ?, 'received')");
    $ins->bind_param('sss', $event, $idempotencyKey, $payloadRaw);
    $ins->execute();

    if ($event === 'transaction.paid') {
        walletHandleTransactionPaidWebhook($conn, $transactionId, $externalReference, $data);

        $orderId = 0;
        if ($externalReference !== '' && preg_match('/^order:(\d+)$/', $externalReference, $m)) {
            $orderId = (int)$m[1];
        }

        if ($orderId > 0) {
            $upOrder = $conn->prepare("UPDATE orders SET status='pago' WHERE id = ?");
            $upOrder->bind_param('i', $orderId);
            $upOrder->execute();

            // Credit admin with buyer service fee
            try {
                $stFee = $conn->prepare("SELECT buyer_fee, user_id FROM orders WHERE id = ? LIMIT 1");
                $stFee->bind_param('i', $orderId);
                $stFee->execute();
                $feeRow = $stFee->get_result()->fetch_assoc();
                $stFee->close();
                $buyerFee = (float)($feeRow['buyer_fee'] ?? 0);
                if ($buyerFee > 0) {
                    $adminReceiver = escrowResolveAdminReceiver($conn);
                    if ($adminReceiver > 0) {
                        $uAdmin = $conn->prepare('UPDATE users SET wallet_saldo = wallet_saldo + ? WHERE id = ?');
                        $uAdmin->bind_param('di', $buyerFee, $adminReceiver);
                        $uAdmin->execute();

                        $descAdm = 'Taxa de serviço (comprador) do pedido #' . $orderId;
                        $txAdm = $conn->prepare("INSERT INTO wallet_transactions (user_id, tipo, origem, referencia_tipo, referencia_id, valor, descricao) VALUES (?, 'credito', 'buyer_service_fee', 'order', ?, ?, ?)");
                        if ($txAdm) {
                            $txAdm->bind_param('iids', $adminReceiver, $orderId, $buyerFee, $descAdm);
                            $txAdm->execute();
                        }
                    }
                }
            } catch (\Throwable $feeErr) {
                error_log('[webhook] buyer_fee credit error: ' . $feeErr->getMessage());
            }

            // Debit wallet if wallet_used > 0
            try {
                $stWu = $conn->prepare("SELECT wallet_used, user_id FROM orders WHERE id = ? LIMIT 1");
                $stWu->bind_param('i', $orderId);
                $stWu->execute();
                $wuRow = $stWu->get_result()->fetch_assoc();
                $stWu->close();
                $walletUsed = (float)($wuRow['wallet_used'] ?? 0);
                $buyerUserId = (int)($wuRow['user_id'] ?? 0);
                if ($walletUsed > 0 && $buyerUserId > 0) {
                    $deb = $conn->prepare('UPDATE users SET wallet_saldo = wallet_saldo - ? WHERE id = ?');
                    $deb->bind_param('di', $walletUsed, $buyerUserId);
                    $deb->execute();

                    $descWd = 'Uso de saldo wallet no checkout do pedido #' . $orderId;
                    $txWd = $conn->prepare("INSERT INTO wallet_transactions (user_id, tipo, origem, referencia_tipo, referencia_id, valor, descricao) VALUES (?, 'debito', 'checkout_wallet', 'order', ?, ?, ?)");
                    if ($txWd) {
                        $txWd->bind_param('iids', $buyerUserId, $orderId, $walletUsed, $descWd);
                        $txWd->execute();
                    }
                }
            } catch (\Throwable $wuErr) {
                error_log('[webhook] wallet_used debit error: ' . $wuErr->getMessage());
            }

            escrowInitializeOrderItems($conn, (int)$orderId);
        }
    }

    $up = $conn->prepare("UPDATE webhook_events SET status='processed', processed_at=NOW() WHERE idempotency_key = ?");
    $up->bind_param('s', $idempotencyKey);
    $up->execute();

    echo json_encode(['ok' => true]);
} catch (Throwable $e) {
    if (str_contains($e->getMessage(), 'Duplicate entry')) {
        http_response_code(200);
        echo json_encode(['ok' => true, 'msg' => 'Evento já processado']);
        exit;
    }

    http_response_code(500);
    echo json_encode(['ok' => false, 'msg' => 'Erro ao processar webhook']);
}
