<?php
// filepath: c:\xampp\htdocs\mercado_admin\public\admin\api_categoria_action.php
declare(strict_types=1);

require_once __DIR__ . '/../../src/auth.php';
require_once __DIR__ . '/../../src/db.php';
require_once __DIR__ . '/../../src/admin_categorias.php';

header('Content-Type: application/json; charset=utf-8');
exigirAdmin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'msg' => 'Método não permitido.']);
    exit;
}

$db = new Database();
$conn = $db->connect();

$action = (string)($_POST['action'] ?? '');
$id = (int)($_POST['id'] ?? 0);

if ($action === 'toggle') {
    $ativo = (int)($_POST['ativo'] ?? -1);
    [$ok, $msg] = alterarAtivoCategoria($conn, $id, $ativo);
    echo json_encode(['ok' => $ok, 'msg' => $msg, 'ativo' => $ativo]);
    exit;
}

if ($action === 'delete') {
    [$ok, $msg] = excluirCategoria($conn, $id);
    echo json_encode(['ok' => $ok, 'msg' => $msg]);
    exit;
}

echo json_encode(['ok' => false, 'msg' => 'Ação inválida.']);