<?php
// filepath: c:\xampp\htdocs\mercado_admin\public\admin\api_venda_action.php
declare(strict_types=1);

ob_start();

require_once __DIR__ . '/../../src/auth.php';
require_once __DIR__ . '/../../src/db.php';
require_once __DIR__ . '/../../src/admin_vendas.php';

exigirAdmin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    ob_end_clean();
    header('Content-Type: application/json; charset=utf-8');
    http_response_code(405);
    echo json_encode(['ok'=>false,'msg'=>'Método não permitido.']);
    exit;
}

$db = new Database();
$conn = $db->connect();

$vendaId = (int)($_POST['id'] ?? 0);
$acao = (string)($_POST['acao'] ?? '');
$motivo = (string)($_POST['motivo'] ?? '');
$adminId = (int)($_SESSION['user_id'] ?? 0);

[$ok, $msg] = decidirVenda($conn, $vendaId, $adminId, $acao, $motivo);

ob_end_clean();
header('Content-Type: application/json; charset=utf-8');
echo json_encode(['ok'=>$ok, 'msg'=>$msg, 'id'=>$vendaId, 'acao'=>$acao]);