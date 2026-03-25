<?php
declare(strict_types=1);
/**
 * Admin — Google OAuth configuration
 */
if (session_status() !== PHP_SESSION_ACTIVE) session_start();

require_once __DIR__ . '/../../src/db.php';
require_once __DIR__ . '/../../src/auth.php';

exigirAdmin();

require_once __DIR__ . '/../../src/google_auth.php';

$conn = (new Database())->connect();

$msg = '';
$err = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $clientId     = trim((string)($_POST['client_id'] ?? ''));
    $clientSecret = trim((string)($_POST['client_secret'] ?? ''));
    $redirectUri  = trim((string)($_POST['redirect_uri'] ?? ''));

    try {
        googleSettingSet($conn, 'client_id', $clientId);
        googleSettingSet($conn, 'client_secret', $clientSecret);
        googleSettingSet($conn, 'redirect_uri', $redirectUri);
        $msg = 'Configurações salvas!';
    } catch (\Throwable $e) {
        $err = 'Erro ao salvar: ' . $e->getMessage();
    }
}

$settings = googleGetAllSettings($conn);
$autoRedirectUri = googleGetRedirectUri($conn);

$pageTitle = 'Google OAuth — Admin';
$activeMenu = 'google_oauth';
include __DIR__ . '/../../views/partials/header.php';
include __DIR__ . '/../../views/partials/admin_layout_start.php';
?>

    <main class="flex-1 p-4 sm:p-6 lg:p-8 max-w-3xl">

        <h1 class="text-2xl font-bold mb-2">Google OAuth</h1>
        <p class="text-sm text-zinc-400 mb-6">Configure o login com Google para as páginas de login e registro.</p>

        <?php if ($msg): ?>
        <div class="mb-4 px-4 py-3 rounded-xl bg-greenx/10 border border-greenx/20 text-greenx text-sm"><?= htmlspecialchars($msg) ?></div>
        <?php endif; ?>
        <?php if ($err): ?>
        <div class="mb-4 px-4 py-3 rounded-xl bg-red-500/10 border border-red-500/20 text-red-400 text-sm"><?= htmlspecialchars($err) ?></div>
        <?php endif; ?>

        <!-- Instructions -->
        <div class="bg-greenx/[0.06] border border-greenx/20 rounded-2xl p-5 mb-6">
            <h3 class="text-sm font-bold text-purple-400 mb-3 flex items-center gap-2">
                <i data-lucide="info" class="w-4 h-4"></i>
                Como configurar
            </h3>
            <ol class="text-sm text-zinc-300 space-y-2 list-decimal list-inside">
                <li>Acesse o <a href="https://console.cloud.google.com/" target="_blank" class="text-purple-400 hover:underline font-medium">Google Cloud Console</a></li>
                <li>Crie um projeto (ou selecione um existente)</li>
                <li>Vá em <strong>APIs &amp; Services → Credentials</strong></li>
                <li>Clique em <strong>"Create Credentials" → "OAuth client ID"</strong></li>
                <li>Tipo de aplicação: <strong>Web application</strong></li>
                <li>Em <strong>"Authorized redirect URIs"</strong>, adicione:
                    <code class="block mt-1 px-3 py-1.5 rounded-lg bg-black/30 text-greenx text-xs font-mono break-all"><?= htmlspecialchars($autoRedirectUri) ?></code>
                </li>
                <li>Copie o <strong>Client ID</strong> e o <strong>Client Secret</strong> gerados</li>
                <li>Cole nos campos abaixo e salve</li>
            </ol>
            <p class="text-xs text-zinc-500 mt-3">Nota: configure também a <strong>OAuth consent screen</strong> (tela de consentimento) com os escopos: <code class="text-zinc-400">email</code>, <code class="text-zinc-400">profile</code>, <code class="text-zinc-400">openid</code>.</p>
        </div>

        <!-- Config form -->
        <form method="post" class="space-y-5">
            <div class="bg-blackx2 border border-blackx3 rounded-2xl p-6 space-y-5">
                <div>
                    <label class="text-sm text-zinc-400 mb-1 block">Client ID *</label>
                    <input type="text" name="client_id" value="<?= htmlspecialchars($settings['client_id']) ?>"
                           class="w-full rounded-xl bg-white/[0.03] border border-white/[0.08] px-4 py-3 text-sm focus:border-greenx/50 focus:outline-none transition-colors font-mono"
                           placeholder="1234567890-xxxxxxx.apps.googleusercontent.com">
                </div>
                <div>
                    <label class="text-sm text-zinc-400 mb-1 block">Client Secret *</label>
                    <input type="password" name="client_secret" value="<?= htmlspecialchars($settings['client_secret']) ?>"
                           class="w-full rounded-xl bg-white/[0.03] border border-white/[0.08] px-4 py-3 text-sm focus:border-greenx/50 focus:outline-none transition-colors font-mono"
                           placeholder="GOCSPX-xxxxxxxxxx">
                </div>
                <div>
                    <label class="text-sm text-zinc-400 mb-1 block">Redirect URI (deixe vazio para auto-detectar)</label>
                    <input type="url" name="redirect_uri" value="<?= htmlspecialchars($settings['redirect_uri']) ?>"
                           class="w-full rounded-xl bg-white/[0.03] border border-white/[0.08] px-4 py-3 text-sm focus:border-greenx/50 focus:outline-none transition-colors font-mono"
                           placeholder="<?= htmlspecialchars($autoRedirectUri) ?>">
                    <p class="text-xs text-zinc-500 mt-1">Auto-detectado: <code class="text-zinc-400"><?= htmlspecialchars($autoRedirectUri) ?></code></p>
                </div>
            </div>

            <div class="flex items-center gap-3">
                <button class="flex items-center gap-2 bg-gradient-to-r from-greenx to-greenxd text-white font-bold px-6 py-3 rounded-xl text-sm hover:from-greenx2 hover:to-greenxd transition-all shadow-lg shadow-greenx/20">
                    <i data-lucide="save" class="w-4 h-4"></i> Salvar configurações
                </button>

                <?php if (googleIsConfigured($conn)): ?>
                <span class="flex items-center gap-1.5 text-greenx text-sm font-medium">
                    <i data-lucide="check-circle-2" class="w-4 h-4"></i> Configurado
                </span>
                <?php else: ?>
                <span class="flex items-center gap-1.5 text-zinc-500 text-sm">
                    <i data-lucide="alert-circle" class="w-4 h-4"></i> Não configurado — botão Google não aparece no login
                </span>
                <?php endif; ?>
            </div>
        </form>

    </main>

<?php
include __DIR__ . '/../../views/partials/admin_layout_end.php';
include __DIR__ . '/../../views/partials/footer.php';
?>
