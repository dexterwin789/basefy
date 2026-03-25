<?php
// filepath: c:\xampp\htdocs\mercado_admin\public\vendedor\api_produto_action.php
declare(strict_types=1);

require_once __DIR__ . '/../../src/auth.php';
require_once __DIR__ . '/../../src/db.php';
require_once __DIR__ . '/../../src/vendor_portal.php';

header('Content-Type: application/json; charset=utf-8');
exigirVendedor();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'msg' => 'MÃ©todo nÃ£o permitido.']);
    exit;
}

$db = new Database();
$conn = $db->connect();

$uid = (int)($_SESSION['user_id'] ?? 0);
$id = (int)($_POST['id'] ?? 0);
$ativo = (int)($_POST['ativo'] ?? -1);

if ($id <= 0 || !in_array($ativo, [0, 1], true)) {
    echo json_encode(['ok' => false, 'msg' => 'ParÃ¢metros invÃ¡lidos.']);
    exit;
}

[$ok, $msg] = toggleMeuProdutoAtivo($conn, $uid, $id, $ativo);
echo json_encode(['ok' => $ok, 'msg' => $msg, 'id' => $id, 'ativo' => $ativo]);

// ...existing code...
include __DIR__ . '/../../views/partials/vendor_layout_end.php';
include __DIR__ . '/../../views/partials/footer.php';
