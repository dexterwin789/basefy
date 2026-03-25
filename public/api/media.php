<?php
// filepath: c:\xampp\htdocs\mercado_admin\public\api\media.php
declare(strict_types=1);

/**
 * Serve media files from the database.
 * Usage: /api/media.php?id=123
 *
 * Includes browser caching headers for performance.
 */

require_once __DIR__ . '/../../src/media.php';

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    http_response_code(400);
    header('Content-Type: text/plain');
    echo 'Missing or invalid id';
    exit;
}

$media = mediaGetData($id);
if (!$media || empty($media['file_data'])) {
    http_response_code(404);
    header('Content-Type: text/plain');
    echo 'Not found';
    exit;
}

$binary = base64_decode($media['file_data'], true);
if ($binary === false) {
    http_response_code(500);
    header('Content-Type: text/plain');
    echo 'Corrupt data';
    exit;
}

$mime = $media['mime_type'] ?: 'application/octet-stream';
$etag = '"' . md5((string)$id . ':' . strlen($binary)) . '"';

// Cache for 30 days
header('Content-Type: ' . $mime);
header('Content-Length: ' . strlen($binary));
header('Cache-Control: public, max-age=2592000, immutable');
header('ETag: ' . $etag);

// 304 Not Modified
if (isset($_SERVER['HTTP_IF_NONE_MATCH']) && trim($_SERVER['HTTP_IF_NONE_MATCH']) === $etag) {
    http_response_code(304);
    exit;
}

echo $binary;
