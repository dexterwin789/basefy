<?php
// filepath: c:\xampp\htdocs\mercado_admin\public\api\notifications.php
declare(strict_types=1);

require_once __DIR__ . '/../../src/auth.php';
require_once __DIR__ . '/../../src/db.php';
require_once __DIR__ . '/../../src/notifications.php';

iniciarSessao();
header('Content-Type: application/json');

function notificationApiSameOriginPost(): bool
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

if (!notificationApiSameOriginPost()) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'msg' => 'Origem inválida.']);
    exit;
}

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['ok' => false, 'msg' => 'Login necessário.', 'login' => true]);
    exit;
}

$userId = (int)$_SESSION['user_id'];
$db = new Database();
$conn = $db->connect();

$action = (string)($_REQUEST['action'] ?? 'list');

if ($action === 'count') {
    $total = notificationsUnreadCount($conn, $userId);
    $byType = notificationsUnreadByType($conn, $userId);
    echo json_encode(['ok' => true, 'total' => $total, 'by_type' => $byType]);
    exit;
}

if ($action === 'list') {
    $limit = min(50, max(1, (int)($_GET['limit'] ?? 20)));
    $offset = max(0, (int)($_GET['offset'] ?? 0));
    $items = notificationsList($conn, $userId, $limit, $offset);
    $total = notificationsUnreadCount($conn, $userId);
    echo json_encode(['ok' => true, 'items' => $items, 'unread' => $total]);
    exit;
}

if ($action === 'read' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $notifId = (int)($_POST['id'] ?? 0);
    if ($notifId > 0) {
        notificationsMarkRead($conn, $notifId, $userId);
    }
    echo json_encode(['ok' => true]);
    exit;
}

if ($action === 'read_all' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $cnt = notificationsMarkAllRead($conn, $userId);
    echo json_encode(['ok' => true, 'marked' => $cnt]);
    exit;
}

echo json_encode(['ok' => false, 'msg' => 'Ação inválida.']);
