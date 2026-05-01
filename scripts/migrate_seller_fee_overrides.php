<?php
declare(strict_types=1);

require_once __DIR__ . '/../src/db.php';

$conn = (new Database())->connect();

function columnExists($conn, string $table, string $column): bool
{
    $st = $conn->prepare('SELECT 1 FROM information_schema.columns WHERE table_schema = current_schema() AND table_name = ? AND column_name = ? LIMIT 1');
    $st->bind_param('ss', $table, $column);
    $st->execute();
    return (bool)$st->get_result()->fetch_assoc();
}

if (!columnExists($conn, 'users', 'seller_fee_override_enabled')) {
    $conn->query('ALTER TABLE users ADD COLUMN seller_fee_override_enabled BOOLEAN NOT NULL DEFAULT FALSE');
    echo "Added users.seller_fee_override_enabled\n";
}

if (!columnExists($conn, 'users', 'seller_fee_percent')) {
    $conn->query('ALTER TABLE users ADD COLUMN seller_fee_percent NUMERIC(5,2) DEFAULT NULL');
    echo "Added users.seller_fee_percent\n";
}

echo "Seller fee override migration OK\n";
