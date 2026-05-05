<?php
// filepath: c:\xampp\htdocs\mercado_admin\public\api\push_subscribe.php
declare(strict_types=1);

require_once __DIR__ . '/../../src/auth.php';
require_once __DIR__ . '/../../src/db.php';
require_once __DIR__ . '/../../src/push.php';

iniciarSessao();
header('Content-Type: application/json');

function pushApiSameOriginPost(): bool
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') return true;
    $origin = trim((string)($_SERVER['HTTP_ORIGIN'] ?? ''));
    if ($origin === '') return true;
    $host = trim((string)($_SERVER['HTTP_HOST'] ?? ''));
    if ($host === '') return false;
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || strtolower((string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https'
        ? 'https'
        : 'http';
    return hash_equals($scheme . '://' . $host, rtrim($origin, '/'));
}

if (!pushApiSameOriginPost()) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'msg' => 'Origem inválida.']);
    exit;
}

$db   = new Database();
$conn = $db->connect();

$action = (string)($_REQUEST['action'] ?? '');

/* ── VAPID public key (no auth needed) ──────────────────────────── */
if ($action === 'vapid_key') {
    $keys = pushGetVapidKeys($conn);
    if ($keys) {
        echo json_encode(['ok' => true, 'publicKey' => $keys['publicKey']]);
    } else {
        echo json_encode(['ok' => false, 'msg' => 'VAPID indisponível.']);
    }
    exit;
}

/* ── Auth required for subscribe / unsubscribe ──────────────────── */
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['ok' => false, 'msg' => 'Login necessário.']);
    exit;
}

$userId = (int)$_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input  = json_decode(file_get_contents('php://input'), true) ?: $_POST;
    $action = (string)($input['action'] ?? '');

    if ($action === 'subscribe') {
        $endpoint = (string)($input['endpoint'] ?? '');
        $p256dh   = (string)($input['p256dh']   ?? '');
        $auth     = (string)($input['auth']     ?? '');

        if (!$endpoint || !$p256dh || !$auth) {
            echo json_encode(['ok' => false, 'msg' => 'Dados incompletos.']);
            exit;
        }

        $ok = pushSaveSubscription($conn, $userId, $endpoint, $p256dh, $auth);
        echo json_encode(['ok' => $ok]);
        exit;
    }

    if ($action === 'unsubscribe') {
        $endpoint = (string)($input['endpoint'] ?? '');
        if ($endpoint) {
            pushRemoveSubscription($conn, $userId, $endpoint);
        }
        echo json_encode(['ok' => true]);
        exit;
    }
}

echo json_encode(['ok' => false, 'msg' => 'Ação inválida.']);
