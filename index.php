<?php
// filepath: c:\xampp\htdocs\mercado_admin\public\index.php
declare(strict_types=1);

require_once __DIR__ . '/../src/auth.php';

if (adminLogado()) {
    header('Location: dashboard.php');
    exit;
}

header('Location: login.php');
exit;