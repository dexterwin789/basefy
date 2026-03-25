<?php
// filepath: c:\xampp\htdocs\mercado_admin\public\admin\api_solicitacao_action.php
declare(strict_types=1);

require_once __DIR__ . '/../../src/auth.php';
require_once __DIR__ . '/../../src/db.php';
require_once __DIR__ . '/../../src/admin_solicitacoes.php';

header('Content-Type: application/json; charset=utf-8');
exigirAdmin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok'=>false,'msg'=>'Método não permitido.']);
    exit;
}

$id = (int)($_POST['id'] ?? 0);
$acao = (string)($_POST['acao'] ?? '');
$motivo = (string)($_POST['motivo'] ?? '');

$db = new Database();
$conn = $db->connect();

[$ok, $msg] = decidirSolicitacaoVendedor($conn, $id, $acao, $motivo);
echo json_encode(['ok'=>$ok,'msg'=>$msg,'id'=>$id,'acao'=>$acao]);