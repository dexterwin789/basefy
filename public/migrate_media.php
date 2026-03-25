<?php
/**
 * Migration: Create media_files table for DB-stored images
 * Access on Railway: https://your-app.railway.app/migrate_media.php
 * DELETE AFTER USE!
 */
declare(strict_types=1);

require_once __DIR__ . '/../src/db.php';

$db   = new Database();
$conn = $db->connect();

$migrations = [
    "CREATE TABLE IF NOT EXISTS media_files (
        id SERIAL PRIMARY KEY,
        entity_type VARCHAR(50) NOT NULL DEFAULT 'product',
        entity_id INT NOT NULL DEFAULT 0,
        file_data TEXT NOT NULL,
        mime_type VARCHAR(100) NOT NULL DEFAULT 'image/jpeg',
        original_name VARCHAR(255),
        is_cover BOOLEAN NOT NULL DEFAULT FALSE,
        sort_order INT NOT NULL DEFAULT 0,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
    )",
    "CREATE INDEX IF NOT EXISTS idx_media_entity ON media_files(entity_type, entity_id)",
    "CREATE INDEX IF NOT EXISTS idx_media_cover ON media_files(entity_type, entity_id, is_cover)",
];

$results = [];
foreach ($migrations as $sql) {
    try {
        $conn->query($sql);
        $results[] = "✅ OK";
    } catch (Throwable $e) {
        $results[] = "❌ " . $e->getMessage();
    }
}

header('Content-Type: text/plain; charset=utf-8');
echo "=== Migração: media_files ===\n\n";
foreach ($results as $i => $r) {
    echo ($i + 1) . ". $r\n";
}
echo "\nDone. DELETE THIS FILE after confirming!\n";
