<?php
declare(strict_types=1);

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

function envOrDefault(string $key, string $default): string
{
    $value = $_ENV[$key] ?? $_SERVER[$key] ?? getenv($key);
    if ($value === false || $value === null || trim((string)$value) === '') {
        return $default;
    }
    return (string)$value;
}

function connectDb(string $host, int $port, string $user, string $pass, string $db): mysqli
{
    $conn = new mysqli($host, $user, $pass, $db, $port);
    $conn->set_charset('utf8mb4');
    return $conn;
}

function getBaseTables(mysqli $conn, string $schema): array
{
    $sql = "SELECT TABLE_NAME
            FROM information_schema.TABLES
            WHERE TABLE_SCHEMA = ? AND TABLE_TYPE = 'BASE TABLE'
            ORDER BY TABLE_NAME";
    $st = $conn->prepare($sql);
    $st->bind_param('s', $schema);
    $st->execute();
    $res = $st->get_result();

    $tables = [];
    while ($row = $res->fetch_assoc()) {
        $tables[] = (string)$row['TABLE_NAME'];
    }
    return $tables;
}

function getColumns(mysqli $conn, string $table): array
{
    $res = $conn->query("SHOW COLUMNS FROM `{$table}`");
    $cols = [];
    while ($row = $res->fetch_assoc()) {
        $cols[] = (string)$row['Field'];
    }
    return $cols;
}

$localHost = envOrDefault('LOCAL_DB_HOST', '127.0.0.1');
$localPort = (int)envOrDefault('LOCAL_DB_PORT', '3306');
$localName = envOrDefault('LOCAL_DB_NAME', 'mercado_admin');
$localUser = envOrDefault('LOCAL_DB_USER', 'root');
$localPass = envOrDefault('LOCAL_DB_PASS', '');

$remoteHost = envOrDefault('DB_HOST', '');
$remotePort = (int)envOrDefault('DB_PORT', '3306');
$remoteName = envOrDefault('DB_DATABASE', '');
$remoteUser = envOrDefault('DB_USERNAME', '');
$remotePass = envOrDefault('DB_PASSWORD', '');

if ($remoteHost === '' || $remoteName === '' || $remoteUser === '') {
    fwrite(STDERR, "Defina DB_HOST, DB_PORT, DB_DATABASE, DB_USERNAME e DB_PASSWORD no ambiente.\n");
    exit(1);
}

try {
    $local = connectDb($localHost, $localPort, $localUser, $localPass, $localName);
    $remote = connectDb($remoteHost, $remotePort, $remoteUser, $remotePass, $remoteName);

    $localTables = getBaseTables($local, $localName);
    $remoteTables = getBaseTables($remote, $remoteName);
    $remoteMap = array_fill_keys($remoteTables, true);

    if (!$localTables) {
        fwrite(STDERR, "Nenhuma tabela encontrada no banco local '{$localName}'.\n");
        exit(1);
    }

    $missing = array_values(array_filter($localTables, static fn(string $t): bool => !isset($remoteMap[$t])));
    if ($missing) {
        fwrite(STDERR, "Tabelas ausentes no remoto: " . implode(', ', $missing) . "\n");
        fwrite(STDERR, "Importe o schema.sql no remoto e rode novamente.\n");
        exit(1);
    }

    $remote->query('SET FOREIGN_KEY_CHECKS=0');

    foreach ($localTables as $table) {
        $remote->query("TRUNCATE TABLE `{$table}`");
    }

    $totalRows = 0;

    foreach ($localTables as $table) {
        $cols = getColumns($local, $table);
        if (!$cols) {
            echo "[{$table}] sem colunas, pulando\n";
            continue;
        }

        $colList = '`' . implode('`,`', $cols) . '`';
        $res = $local->query("SELECT * FROM `{$table}`", MYSQLI_USE_RESULT);

        $count = 0;
        while ($row = $res->fetch_assoc()) {
            $vals = [];
            foreach ($cols as $col) {
                $v = $row[$col] ?? null;
                if ($v === null) {
                    $vals[] = 'NULL';
                } else {
                    $vals[] = "'" . $remote->real_escape_string((string)$v) . "'";
                }
            }

            $sql = "INSERT INTO `{$table}` ({$colList}) VALUES (" . implode(',', $vals) . ")";
            $remote->query($sql);
            $count++;
        }
        $res->free();

        $totalRows += $count;
        echo "[{$table}] {$count} linha(s) migrada(s)\n";
    }

    $remote->query('SET FOREIGN_KEY_CHECKS=1');

    echo "\nMigração concluída com sucesso. Total de linhas: {$totalRows}\n";
    exit(0);
} catch (Throwable $e) {
    fwrite(STDERR, "Erro: " . $e->getMessage() . "\n");
    exit(1);
}
