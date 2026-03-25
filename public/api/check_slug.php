<?php
declare(strict_types=1);

require_once __DIR__ . '/../../src/config.php';
require_once __DIR__ . '/../../src/db.php';
require_once __DIR__ . '/../../src/storefront.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$type = trim((string)($_GET['type'] ?? ''));       // product | category | vendor
$slug = trim((string)($_GET['slug'] ?? ''));
$excludeId = (int)($_GET['exclude_id'] ?? 0);

if ($slug === '' || !in_array($type, ['product', 'category', 'vendor'], true)) {
    echo json_encode(['available' => false, 'slug' => '', 'suggestion' => '', 'error' => 'Parâmetros inválidos']);
    exit;
}

$conn = (new Database())->connect();
$result = sfCheckSlugAvailable($conn, $type, $slug, $excludeId);
echo json_encode($result);
