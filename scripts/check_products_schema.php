<?php
require_once __DIR__ . '/../src/db.php';
$db = new Database();
$c = $db->connect();
$r = $c->query('SHOW COLUMNS FROM products');
while ($row = $r->fetch_assoc()) {
    echo $row['Field'] . ' | ' . $row['Type'] . ' | ' . ($row['Default'] ?? 'NULL') . "\n";
}
