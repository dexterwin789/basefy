<?php
// filepath: c:\xampp\htdocs\mercado_admin\public\logout.php
declare(strict_types=1);

session_start();
$_SESSION = [];
session_destroy();

header('Location: login');
exit;