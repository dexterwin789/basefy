<?php
// filepath: c:\xampp\htdocs\mercado_admin\scripts\create_admin.php
declare(strict_types=1);

require_once __DIR__ . '/../src/db.php';

if (PHP_SAPI !== 'cli') {
    exit("Uso permitido apenas no terminal.\n");
}

$nome = $argv[1] ?? '';
$email = $argv[2] ?? '';
$senha = $argv[3] ?? '';

if ($nome === '' || $email === '' || $senha === '') {
    exit("Uso: php scripts/create_admin.php \"Nome\" email@dominio.com \"SenhaForte123\"\n");
}

$db = new Database();
$conn = $db->connect();

$hash = password_hash($senha, PASSWORD_DEFAULT);

$stmt = $conn->prepare(
    'INSERT INTO users (nome, email, senha, avatar, role, is_vendedor, status_vendedor)
     VALUES (?, ?, ?, NULL, "admin", 0, "nao_solicitado")'
);
$stmt->bind_param('sss', $nome, $email, $hash);
$stmt->execute();

echo "Admin criado com sucesso.\n";