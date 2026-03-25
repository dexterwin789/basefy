<?php
/**
 * Migration: Add tipo, quantidade, prazo_entrega_dias, data_entrega to products
 * Run this once via browser: /mercado_admin/scripts/migrate_product_type_qty.php
 */
declare(strict_types=1);

require_once __DIR__ . '/../src/db.php';

$db   = new Database();
$conn = $db->connect();

$migrations = [
    "ALTER TABLE products ADD COLUMN IF NOT EXISTS tipo VARCHAR(20) NOT NULL DEFAULT 'produto'",
    "ALTER TABLE products ADD COLUMN IF NOT EXISTS quantidade INT NOT NULL DEFAULT 0",
    "ALTER TABLE products ADD COLUMN IF NOT EXISTS prazo_entrega_dias INT NULL",
    "ALTER TABLE products ADD COLUMN IF NOT EXISTS data_entrega DATE NULL",
    "CREATE INDEX IF NOT EXISTS idx_products_tipo ON products(tipo)",
];

$results = [];
foreach ($migrations as $sql) {
    try {
        $conn->query($sql);
        $results[] = "[OK] $sql";
    } catch (Throwable $e) {
        $results[] = "[ERRO] $sql => " . $e->getMessage();
    }
}

header('Content-Type: text/plain; charset=utf-8');
echo "=== Migração: Produto/Serviço + Quantidade + Prazo ===\n\n";
echo implode("\n", $results) . "\n\nDone.\n";
