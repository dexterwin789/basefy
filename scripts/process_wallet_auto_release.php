<?php
declare(strict_types=1);

require_once __DIR__ . '/../src/db.php';
require_once __DIR__ . '/../src/wallet_escrow.php';

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("Use apenas via CLI.\n");
}

$conn = (new Database())->connect();
[$ok, $msg, $released] = escrowProcessAutoReleases($conn, 200);

echo ($ok ? '[OK] ' : '[ERRO] ') . $msg . PHP_EOL;
echo 'Itens liberados: ' . (int)$released . PHP_EOL;
