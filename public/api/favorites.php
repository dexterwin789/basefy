<?php
// filepath: c:\xampp\htdocs\mercado_admin\public\api\favorites.php
declare(strict_types=1);

require_once __DIR__ . '/../../src/auth.php';
require_once __DIR__ . '/../../src/db.php';
require_once __DIR__ . '/../../src/favorites.php';

iniciarSessao();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['ok' => false, 'msg' => 'Faça login primeiro.', 'login' => true]);
    exit;
}

$userId = (int)$_SESSION['user_id'];
$db = new Database();
$conn = $db->connect();

$action = (string)($_REQUEST['action'] ?? '');

if ($action === 'toggle') {
    $productId = (int)($_POST['product_id'] ?? 0);
    if ($productId <= 0) {
        echo json_encode(['ok' => false, 'msg' => 'Produto inválido.']);
        exit;
    }
    [$isFav, $msg] = favoritesToggle($conn, $userId, $productId);
    echo json_encode(['ok' => true, 'favorited' => $isFav, 'msg' => $msg]);
    exit;
}

if ($action === 'check') {
    $productId = (int)($_GET['product_id'] ?? 0);
    if ($productId <= 0) {
        echo json_encode(['ok' => false, 'msg' => 'Produto inválido.']);
        exit;
    }
    $isFav = favoritesCheck($conn, $userId, $productId);
    echo json_encode(['ok' => true, 'favorited' => $isFav]);
    exit;
}

if ($action === 'check_bulk') {
    $ids = json_decode((string)($_GET['ids'] ?? '[]'), true);
    if (!is_array($ids)) $ids = [];
    $ids = array_map('intval', $ids);
    $favIds = favoritesCheckBulk($conn, $userId, $ids);
    echo json_encode(['ok' => true, 'favorited_ids' => $favIds]);
    exit;
}

if ($action === 'count') {
    $count = favoritesCount($conn, $userId);
    echo json_encode(['ok' => true, 'count' => $count]);
    exit;
}

echo json_encode(['ok' => false, 'msg' => 'Ação inválida.']);
