<?php
declare(strict_types=1);
/**
 * Google OAuth redirect — sets mode/role in session and redirects to Google.
 * 
 * Query parameters:
 *   mode      — 'login' or 'register' (default: login)
 *   role      — 'comprador' or 'vendedor' (used only when mode=register, default: comprador)
 *   return_to — URL to return to after login (optional)
 */

require_once __DIR__ . '/../src/auth.php';
require_once __DIR__ . '/../src/google_auth.php';

iniciarSessao();

$db   = new Database();
$conn = $db->connect();

if (!googleIsConfigured($conn)) {
    header('Location: ' . BASE_PATH . '/login?google_error=' . urlencode('Google OAuth não está configurado.'));
    exit;
}

$mode     = (string)($_GET['mode'] ?? 'login');
$role     = (string)($_GET['role'] ?? 'comprador');
$returnTo = (string)($_GET['return_to'] ?? '');

// Validate
if (!in_array($mode, ['login', 'register'], true)) {
    $mode = 'login';
}
if (!in_array($role, ['comprador', 'vendedor'], true)) {
    $role = 'comprador';
}

$googleUrl = googleAuthUrl($conn, $returnTo, $mode, $role);

header('Location: ' . $googleUrl);
exit;
