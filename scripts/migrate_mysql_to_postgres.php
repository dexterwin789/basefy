<?php
declare(strict_types=1);

error_reporting(E_ALL);
ini_set('display_errors', '1');

function envVal(string $key, string $default = ''): string
{
    $v = $_ENV[$key] ?? $_SERVER[$key] ?? getenv($key);
    return ($v === false || $v === null) ? $default : (string)$v;
}

$mysqlHost = envVal('MYSQL_HOST', envVal('LOCAL_DB_HOST', '127.0.0.1'));
$mysqlPort = (int)envVal('MYSQL_PORT', envVal('LOCAL_DB_PORT', '3306'));
$mysqlDb = envVal('MYSQL_DATABASE', envVal('LOCAL_DB_NAME', 'mercado_admin'));
$mysqlUser = envVal('MYSQL_USER', envVal('LOCAL_DB_USER', 'root'));
$mysqlPass = envVal('MYSQL_PASSWORD', envVal('LOCAL_DB_PASS', ''));

$pgHost = envVal('PGHOST', envVal('POSTGRES_HOST', ''));
$pgPort = (int)envVal('PGPORT', envVal('POSTGRES_PORT', '5432'));
$pgDb = envVal('PGDATABASE', envVal('POSTGRES_DB', 'railway'));
$pgUser = envVal('PGUSER', envVal('POSTGRES_USER', 'postgres'));
$pgPass = envVal('PGPASSWORD', envVal('POSTGRES_PASSWORD', ''));

if ($pgHost === '' || $pgUser === '' || $pgDb === '') {
    fwrite(STDERR, "Defina PGHOST/PGPORT/PGDATABASE/PGUSER/PGPASSWORD (ou POSTGRES_*) no ambiente.\n");
    exit(1);
}

$tablesOrder = [
    'users', 'categories', 'products', 'orders', 'order_items', 'platform_settings',
    'payment_transactions', 'webhook_events', 'seller_requests', 'seller_profiles',
    'wallet_withdrawals', 'wallet_transactions', 'wallets', 'sales', 'sale_action_logs',
    'admins', 'usuarios', 'vendedores'
];

try {
    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
    $my = new mysqli($mysqlHost, $mysqlUser, $mysqlPass, $mysqlDb, $mysqlPort);
    $my->set_charset('utf8mb4');

    $pg = new PDO(
        "pgsql:host={$pgHost};port={$pgPort};dbname={$pgDb}",
        $pgUser,
        $pgPass,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]
    );

    $schemaPath = __DIR__ . '/../sql/schema.postgres.sql';
    if (!is_file($schemaPath)) {
        throw new RuntimeException('Arquivo sql/schema.postgres.sql não encontrado.');
    }

    $schemaSql = file_get_contents($schemaPath);
    if ($schemaSql === false) {
        throw new RuntimeException('Falha ao ler sql/schema.postgres.sql.');
    }

    $pg->exec($schemaSql);
    $pg->beginTransaction();
    $pg->exec("SET session_replication_role = replica");

    foreach (array_reverse($tablesOrder) as $table) {
        $pg->exec("TRUNCATE TABLE \"{$table}\" RESTART IDENTITY CASCADE");
    }

    $total = 0;

    foreach ($tablesOrder as $table) {
        $res = $my->query("SELECT * FROM `{$table}`");
        $rows = $res->fetch_all(MYSQLI_ASSOC);

        if (!$rows) {
            echo "[{$table}] 0 linha(s)\n";
            continue;
        }

        $cols = array_keys($rows[0]);
        $quotedCols = array_map(static fn(string $c): string => '"' . str_replace('"', '""', $c) . '"', $cols);
        $placeholders = array_map(static fn(string $c): string => ':' . $c, $cols);

        $sql = sprintf(
            'INSERT INTO "%s" (%s) VALUES (%s)',
            $table,
            implode(',', $quotedCols),
            implode(',', $placeholders)
        );

        $st = $pg->prepare($sql);

        $count = 0;
        foreach ($rows as $row) {
            foreach ($cols as $col) {
                $value = $row[$col];
                $st->bindValue(':' . $col, $value, $value === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
            }
            $st->execute();
            $count++;
            $total++;
        }

        echo "[{$table}] {$count} linha(s)\n";
    }

    $pg->exec("SET session_replication_role = origin");
    $pg->commit();

    echo "\nMigração MySQL -> PostgreSQL concluída com sucesso. Total: {$total} linha(s).\n";
    exit(0);
} catch (Throwable $e) {
    if (isset($pg) && $pg instanceof PDO && $pg->inTransaction()) {
        $pg->rollBack();
    }
    fwrite(STDERR, 'Erro: ' . $e->getMessage() . PHP_EOL);
    exit(1);
}
