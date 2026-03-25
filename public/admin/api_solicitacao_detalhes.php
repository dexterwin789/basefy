<?php
// filepath: c:\xampp\htdocs\mercado_admin\public\admin\api_solicitacao_detalhes.php
declare(strict_types=1);

require_once __DIR__ . '/../../src/auth.php';
require_once __DIR__ . '/../../src/db.php';

header('Content-Type: application/json; charset=utf-8');
exigirAdmin();

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    echo json_encode(['ok' => false, 'msg' => 'ID inválido.']);
    exit;
}

$db = new Database();
$conn = $db->connect();

$stmt = $conn->prepare("SELECT sr.*, u.id AS user_id, u.nome, u.email, u.role, u.status_vendedor
                        FROM seller_requests sr
                        INNER JOIN users u ON u.id = sr.user_id
                        WHERE sr.id = ?
                        LIMIT 1");
$stmt->bind_param('i', $id);
$stmt->execute();
$sol = $stmt->get_result()->fetch_assoc();

if (!$sol) {
    echo json_encode(['ok' => false, 'msg' => 'Solicitação não encontrada.']);
    exit;
}

$stmt2 = $conn->prepare("SELECT * FROM seller_profiles WHERE user_id = ? LIMIT 1");
$stmt2->bind_param('i', $sol['user_id']);
$stmt2->execute();
$profile = $stmt2->get_result()->fetch_assoc();

echo json_encode([
    'ok' => true,
    'solicitacao' => $sol,
    'perfil' => $profile ?: new stdClass(),
]);