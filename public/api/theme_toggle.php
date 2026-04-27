<?php
declare(strict_types=1);
/**
 * API: Theme state endpoint. Basefy now runs a single dark theme.
 */
if (session_status() !== PHP_SESSION_ACTIVE) session_start();

require_once __DIR__ . '/../../src/db.php';
require_once __DIR__ . '/../../src/theme.php';

header('Content-Type: application/json; charset=UTF-8');

$input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
$conn = (new Database())->connect();

themeSettingSet($conn, 'active_theme', 'basefy');
themeSettingSet($conn, 'color_mode', 'dark');

echo json_encode([
    'ok'       => true,
    'active'   => themeGetActive($conn),
    'tailwind' => themeTailwindColors($conn),
]);
