<?php
declare(strict_types=1);
/**
 * API: Toggle theme color mode (dark/light) or active theme.
 * POST { mode?: 'dark'|'light', theme?: 'green'|'blue' }
 */
if (session_status() !== PHP_SESSION_ACTIVE) session_start();

require_once __DIR__ . '/../../src/db.php';
require_once __DIR__ . '/../../src/theme.php';

header('Content-Type: application/json; charset=UTF-8');

$input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
$conn = (new Database())->connect();

if (isset($input['mode']) && in_array($input['mode'], ['dark', 'light'], true)) {
    themeSettingSet($conn, 'color_mode', $input['mode']);
}

if (isset($input['theme'])) {
    $defs = themeDefinitions();
    if (isset($defs[$input['theme']])) {
        themeSettingSet($conn, 'active_theme', $input['theme']);
    }
}

echo json_encode([
    'ok'       => true,
    'active'   => themeGetActive($conn),
    'tailwind' => themeTailwindColors($conn),
]);
