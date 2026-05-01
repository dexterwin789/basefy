<?php
declare(strict_types=1);

require_once __DIR__ . '/../../src/db.php';
require_once __DIR__ . '/../../src/wallet_escrow.php';
require_once __DIR__ . '/../../src/wallet_portal.php';

header('Content-Type: application/json; charset=utf-8');

$payloadRaw = file_get_contents('php://input') ?: '{}';

// ---------------------------------------------------------------------------
// HMAC signature verification (optional — only enforced if secret configured)
// Backwards-compatible: when BLACKCAT_WEBHOOK_SECRET env/setting is empty,
// signature is skipped so existing flow keeps working until the secret is set.
// ---------------------------------------------------------------------------
$webhookSecret = (string)(getenv('BLACKCAT_WEBHOOK_SECRET') ?: '');
if ($webhookSecret === '' && function_exists('getSetting')) {
    try { $webhookSecret = (string)getSetting('blackcat.webhook_secret', ''); } catch (\Throwable $e) {}
}
if ($webhookSecret !== '') {
    $sigHeader = (string)(
        $_SERVER['HTTP_X_SIGNATURE']
        ?? $_SERVER['HTTP_X_WEBHOOK_SIGNATURE']
        ?? $_SERVER['HTTP_X_HUB_SIGNATURE_256']
        ?? ''
    );
    // Strip optional "sha256=" prefix
    if (stripos($sigHeader, 'sha256=') === 0) $sigHeader = substr($sigHeader, 7);

    $expected = hash_hmac('sha256', $payloadRaw, $webhookSecret);
    if ($sigHeader === '' || !hash_equals($expected, strtolower(trim($sigHeader)))) {
        error_log('[webhook] BlackCat HMAC inválida — payload rejeitado');
        http_response_code(401);
        echo json_encode(['ok' => false, 'msg' => 'Assinatura inválida']);
        exit;
    }
}

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

// Idempotency: stable across BlackCat retries (timestamp removed — antes
// re-processava porque cada retry vinha com timestamp diferente, causando
// double-debit de wallet e double-credit de fee).
$idempotencyKey = sha1($event . '|' . $transactionId . '|' . $status);

try {
    $ins = $conn->prepare("INSERT INTO webhook_events (provider, event_name, idempotency_key, payload, status)
                           VALUES ('blackcat', ?, ?, ?, 'received')");
    $ins->bind_param('sss', $event, $idempotencyKey, $payloadRaw);
    $ins->execute();

    if ($event === 'transaction.paid') {
        // Master transaction: tudo abaixo é all-or-nothing. Se qualquer passo
        // falha, rollback para evitar pedido meio-pago.
        $conn->begin_transaction();
        try {
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
                $stFee = $conn->prepare("SELECT buyer_fee, wallet_used, user_id FROM orders WHERE id = ? LIMIT 1");
                $stFee->bind_param('i', $orderId);
                $stFee->execute();
                $feeRow = $stFee->get_result()->fetch_assoc() ?: [];
                $stFee->close();

                $buyerFee    = (float)($feeRow['buyer_fee']   ?? 0);
                $walletUsed  = (float)($feeRow['wallet_used'] ?? 0);
                $buyerUserId = (int)  ($feeRow['user_id']     ?? 0);

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
                    } else {
                        error_log('[webhook] buyer_fee não creditada — nenhum admin receiver disponível (pedido #' . $orderId . ')');
                    }
                }

                // Debit wallet if wallet_used > 0  — saldo guard previne saldo negativo
                if ($walletUsed > 0 && $buyerUserId > 0) {
                    $deb = $conn->prepare('UPDATE users SET wallet_saldo = wallet_saldo - ? WHERE id = ? AND wallet_saldo >= ?');
                    $deb->bind_param('did', $walletUsed, $buyerUserId, $walletUsed);
                    $deb->execute();
                    if ($deb->affected_rows === 0) {
                        // Saldo insuficiente — não debita mas não derruba o pagamento;
                        // loga para auditoria (race condition entre checkout e webhook).
                        error_log('[webhook] wallet debit BLOQUEADO por saldo insuficiente — order #' . $orderId . ' user #' . $buyerUserId . ' valor=' . $walletUsed);
                    } else {
                        $descWd = 'Uso de saldo wallet no checkout do pedido #' . $orderId;
                        $txWd = $conn->prepare("INSERT INTO wallet_transactions (user_id, tipo, origem, referencia_tipo, referencia_id, valor, descricao) VALUES (?, 'debito', 'checkout_wallet', 'order', ?, ?, ?)");
                        if ($txWd) {
                            $txWd->bind_param('iids', $buyerUserId, $orderId, $walletUsed, $descWd);
                            $txWd->execute();
                        }
                    }
                }

                escrowInitializeOrderItems($conn, (int)$orderId);
            }

            $conn->commit();
        } catch (\Throwable $txErr) {
            $conn->rollback();
            throw $txErr;
        }
    }

    $up = $conn->prepare("UPDATE webhook_events SET status='processed', processed_at=NOW() WHERE idempotency_key = ?");
    $up->bind_param('s', $idempotencyKey);
    $up->execute();

    echo json_encode(['ok' => true]);
} catch (Throwable $e) {
    if (str_contains($e->getMessage(), 'Duplicate entry')) {
        // Retry da BlackCat com a mesma idempotency_key — tudo certo, já processamos.
        http_response_code(200);
        echo json_encode(['ok' => true, 'msg' => 'Evento já processado']);
        exit;
    }

    error_log('[webhook] BlackCat erro fatal: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['ok' => false, 'msg' => 'Erro ao processar webhook']);
}
