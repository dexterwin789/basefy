<?php
/**
 * Migration: Add tipo_chave, transaction_id columns and expand observacao to TEXT
 * on wallet_withdrawals table.
 *
 * Run once: GET {BASE_PATH}/migrate_wallet_withdrawals.php
 */
declare(strict_types=1);

require_once __DIR__ . '/../src/db.php';

$conn = (new Database())->connect();
$results = [];

$migrations = [
    "ALTER TABLE wallet_withdrawals ADD COLUMN IF NOT EXISTS tipo_chave VARCHAR(30)",
    "ALTER TABLE wallet_withdrawals ADD COLUMN IF NOT EXISTS transaction_id VARCHAR(60)",
    "ALTER TABLE wallet_withdrawals ALTER COLUMN observacao TYPE TEXT",
];

foreach ($migrations as $sql) {
    try {
        $conn->query($sql);
        $results[] = "OK: {$sql}";
    } catch (Throwable $e) {
        $results[] = "WARN: {$sql} — " . $e->getMessage();
    }
}

// Generate TRX IDs for existing rows that don't have one
try {
    $st = $conn->query("SELECT id FROM wallet_withdrawals WHERE transaction_id IS NULL OR transaction_id = ''");
    $rows = $st ? $st->fetch_all(MYSQLI_ASSOC) : [];
    $count = 0;
    foreach ($rows as $row) {
        $trxId = 'TRX' . time() . strtoupper(bin2hex(random_bytes(4)));
        $up = $conn->prepare("UPDATE wallet_withdrawals SET transaction_id = ? WHERE id = ?");
        $up->bind_param('si', $trxId, $row['id']);
        $up->execute();
        $up->close();
        $count++;
        usleep(1000); // tiny sleep to ensure unique timestamps
    }
    $results[] = "OK: Generated TRX IDs for {$count} existing withdrawals.";
} catch (Throwable $e) {
    $results[] = "WARN: TRX backfill — " . $e->getMessage();
}

header('Content-Type: text/plain; charset=utf-8');
echo "=== wallet_withdrawals migration ===\n\n";
echo implode("\n", $results) . "\n\nDone.\n";
