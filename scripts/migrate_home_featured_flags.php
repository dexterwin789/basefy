<?php
declare(strict_types=1);

require_once __DIR__ . '/../src/db.php';

$conn = (new Database())->connect();

function homeFeaturedColumnExists($conn, string $table, string $column): bool
{
    $st = $conn->prepare('SELECT 1 FROM information_schema.columns WHERE table_schema = current_schema() AND table_name = ? AND column_name = ? LIMIT 1');
    $st->bind_param('ss', $table, $column);
    $st->execute();
    return (bool)$st->get_result()->fetch_assoc();
}

if (!homeFeaturedColumnExists($conn, 'categories', 'destaque')) {
    $conn->query('ALTER TABLE categories ADD COLUMN destaque BOOLEAN NOT NULL DEFAULT FALSE');
    echo "Added categories.destaque\n";
}

if (!homeFeaturedColumnExists($conn, 'products', 'destaque')) {
    $conn->query('ALTER TABLE products ADD COLUMN destaque BOOLEAN NOT NULL DEFAULT FALSE');
    echo "Added products.destaque\n";
}

echo "Home featured flags migration OK\n";
