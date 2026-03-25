<?php
/**
 * API: Real-time CPF / Phone uniqueness check
 * GET ?type=cpf|telefone&value=xxx&exclude_id=N
 * Returns JSON { available: bool, message: string }
 */
declare(strict_types=1);

require_once __DIR__ . '/../../src/config.php';
require_once __DIR__ . '/../../src/db.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$type      = trim((string)($_GET['type'] ?? ''));
$value     = trim((string)($_GET['value'] ?? ''));
$excludeId = (int)($_GET['exclude_id'] ?? 0);

if (!in_array($type, ['cpf', 'telefone'], true) || $value === '') {
    echo json_encode(['available' => false, 'message' => 'Parâmetros inválidos.']);
    exit;
}

$conn = (new Database())->connect();

// Detect columns
$column = null;
try {
    $rs = $conn->query("SELECT column_name FROM information_schema.columns WHERE table_name = 'users'");
    if ($rs) {
        while ($r = $rs->fetch_assoc()) {
            $col = strtolower((string)($r['column_name'] ?? ''));
            if ($type === 'cpf' && in_array($col, ['cpf', 'documento'], true)) {
                $column = $col;
                break;
            }
            if ($type === 'telefone' && in_array($col, ['telefone', 'phone'], true)) {
                $column = $col;
                break;
            }
        }
    }
} catch (\Throwable $e) {}

if (!$column) {
    echo json_encode(['available' => true, 'message' => '']);
    exit;
}

// For CPF, normalize to masked format for comparison
$searchValue = $value;
if ($type === 'cpf') {
    $raw = preg_replace('/\D/', '', $value);
    if (strlen($raw) === 11) {
        $searchValue = substr($raw, 0, 3) . '.' . substr($raw, 3, 3) . '.' . substr($raw, 6, 3) . '-' . substr($raw, 9, 2);
    }
    // Also check raw format
    $st = $conn->prepare("SELECT id FROM users WHERE ({$column} = ? OR {$column} = ?) AND id != ? LIMIT 1");
    $st->bind_param('ssi', $searchValue, $raw, $excludeId);
} else {
    $st = $conn->prepare("SELECT id FROM users WHERE {$column} = ? AND id != ? LIMIT 1");
    $st->bind_param('si', $searchValue, $excludeId);
}

$st->execute();
$exists = (bool)$st->get_result()->fetch_assoc();
$st->close();

if ($exists) {
    $label = $type === 'cpf' ? 'CPF' : 'Telefone';
    echo json_encode([
        'available' => false,
        'message'   => "{$label} já cadastrado em outra conta.",
    ]);
} else {
    $label = $type === 'cpf' ? 'CPF' : 'Telefone';
    echo json_encode([
        'available' => true,
        'message'   => "{$label} disponível.",
    ]);
}
