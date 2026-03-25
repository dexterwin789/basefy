<?php
declare(strict_types=1);
/**
 * Google OAuth callback — handles the redirect after Google sign-in.
 */

require_once __DIR__ . '/../src/auth.php';
require_once __DIR__ . '/../src/google_auth.php';

iniciarSessao();

$db   = new Database();
$conn = $db->connect();

$error = '';

// Retrieve flow context from session
$oauthMode = (string)($_SESSION['google_oauth_mode'] ?? 'login');
$oauthRole = (string)($_SESSION['google_oauth_role'] ?? 'comprador');
unset($_SESSION['google_oauth_mode'], $_SESSION['google_oauth_role']);

// Verify state token (CSRF)
$state = (string)($_GET['state'] ?? '');
$sessionState = (string)($_SESSION['google_oauth_state'] ?? '');
if ($state === '' || $state !== $sessionState) {
    $error = 'Estado inválido. Tente novamente.';
}
unset($_SESSION['google_oauth_state']);

// Check for error from Google
if (!$error && !empty($_GET['error'])) {
    $error = 'Login cancelado ou erro do Google: ' . htmlspecialchars((string)$_GET['error']);
}

// Exchange code
$code = (string)($_GET['code'] ?? '');
if (!$error && $code === '') {
    $error = 'Código de autorização não recebido.';
}

$googleUser = null;
if (!$error) {
    $googleUser = googleExchangeCode($conn, $code);
    if (!$googleUser) {
        $error = 'Não foi possível obter dados do Google. Tente novamente.';
    }
}

// Login or register based on mode
if (!$error && $googleUser) {
    if ($oauthMode === 'register') {
        [$ok, $result] = googleRegister($conn, $googleUser, $oauthRole);
    } else {
        [$ok, $result] = googleLogin($conn, $googleUser);
    }
    if (!$ok) {
        $error = is_string($result) ? $result : 'Erro ao autenticar.';
    }
}

// Redirect back on error — go to login or register page depending on mode
if ($error) {
    $errorPage = $oauthMode === 'register' ? '/register' : '/login';
    header('Location: ' . BASE_PATH . $errorPage . '?google_error=' . urlencode($error));
    exit;
}

// Success — redirect to appropriate dashboard
$returnTo = (string)($_SESSION['google_return_to'] ?? '');
unset($_SESSION['google_return_to']);

if ($returnTo !== '' && str_starts_with($returnTo, '/')) {
    header('Location: ' . $returnTo);
    exit;
}

// Redirect based on role
$user = $_SESSION['user'] ?? [];
$role = normalizarRole((string)($user['role'] ?? 'usuario'));

if ($role === 'admin') {
    header('Location: ' . BASE_PATH . '/admin/dashboard');
    exit;
}

// Unified dashboard for all users
header('Location: ' . BASE_PATH . '/dashboard');
exit;
