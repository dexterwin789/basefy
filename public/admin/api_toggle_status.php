<?php
// filepath: c:\xampp\htdocs\mercado_admin\public\admin\api_toggle_status.php
declare(strict_types=1);

require_once __DIR__ . '/../../src/auth.php';
require_once __DIR__ . '/../../src/db.php';
require_once __DIR__ . '/../../src/admin_users.php';

header('Content-Type: application/json; charset=utf-8');
exigirAdmin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'msg' => 'Método não permitido.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$id = (int)($_POST['id'] ?? 0);
$ativo = (int)($_POST['ativo'] ?? -1);
$scope = (string)($_POST['scope'] ?? 'usuarios');

$rolesPermitidos = match ($scope) {
    'vendedores' => ['vendedor'],
    'usuarios' => ['comprador'],
    default => ['comprador'],
};

$db = new Database();
$conn = $db->connect();

[$ok, $msg] = atualizarStatusAtivoUsuario($conn, $id, $ativo, $rolesPermitidos);

echo json_encode([
    'ok' => (bool)$ok,
    'msg' => (string)$msg,
    'ativo' => $ativo
], JSON_UNESCAPED_UNICODE);
exit;