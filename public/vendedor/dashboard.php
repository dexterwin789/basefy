<?php
// filepath: c:\xampp\htdocs\mercado_admin\public\vendedor\dashboard.php
declare(strict_types=1);

// Redirect to unified dashboard
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
$ROOT = dirname(__DIR__, 2);
require_once $ROOT . '/src/auth.php';
exigirLogin();
header('Location: ' . (defined('BASE_PATH') ? BASE_PATH : '') . '/dashboard');
exit;
// Dead code below — file now redirects to /dashboard
