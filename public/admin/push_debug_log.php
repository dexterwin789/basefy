<?php
// filepath: c:\xampp\htdocs\mercado_admin\public\admin\push_debug_log.php
declare(strict_types=1);

require_once __DIR__ . '/../../src/auth.php';
require_once __DIR__ . '/../../src/db.php';
require_once __DIR__ . '/../../src/push_debug.php';

iniciarSessao();
exigirAdmin();

$conn = (new Database())->connect();

// Handle clear action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'clear') {
    pushDebugClearLog();
    header('Location: push_debug_log');
    exit;
}

$logContent = pushDebugReadLog(200);
$lineCount  = substr_count($logContent, "\n") + 1;

$pageTitle  = 'Push Debug Log';
$activeMenu = 'push_config';
include __DIR__ . '/../../views/partials/header.php';
include __DIR__ . '/../../views/partials/admin_layout_start.php';
?>

<div class="space-y-4">
    <div class="flex items-center justify-between">
        <h2 class="text-lg font-bold inline-flex items-center gap-2">
            <i data-lucide="file-text" class="w-5 h-5 text-purple-400"></i>
            Push Debug Log
        </h2>
        <div class="flex items-center gap-2">
            <a href="push_config" class="px-3 py-1.5 rounded-lg border border-blackx3 text-xs text-zinc-400 hover:text-greenx transition-all">&larr; Push Config</a>
            <form method="post" onsubmit="return confirm('Limpar todo o log?')">
                <input type="hidden" name="action" value="clear">
                <button class="px-3 py-1.5 rounded-lg border border-red-500/30 text-xs text-red-400 hover:bg-red-500/10 transition-all">Limpar Log</button>
            </form>
            <button onclick="location.reload()" class="px-3 py-1.5 rounded-lg border border-blackx3 text-xs text-zinc-400 hover:text-greenx transition-all">
                <i data-lucide="refresh-cw" class="w-3 h-3 inline"></i> Atualizar
            </button>
        </div>
    </div>

    <div class="text-xs text-zinc-500 mb-2"><?= $lineCount ?> linhas | Últimas 200 entradas</div>

    <div class="bg-blackx2 border border-blackx3 rounded-2xl p-4">
        <pre class="text-[11px] leading-5 text-zinc-300 font-mono whitespace-pre-wrap break-all max-h-[80vh] overflow-y-auto"><?= htmlspecialchars($logContent) ?></pre>
    </div>

    <div class="bg-blackx2 border border-blackx3 rounded-2xl p-4 text-sm text-zinc-400">
        <h3 class="font-bold text-zinc-300 mb-2">Como testar:</h3>
        <ol class="list-decimal ml-5 space-y-1">
            <li>Limpe o log (botão acima)</li>
            <li>Vá em <a href="solicitacoes_produto" class="text-greenx underline">Solicitações de Produto</a> e aprove/recuse um produto</li>
            <li>Volte aqui e clique "Atualizar" para ver o log</li>
            <li>Procure por linhas com ✗ (falha) ou veja onde a cadeia parou</li>
        </ol>
        <p class="mt-2 text-zinc-500">Se o log aparecer vazio após aprovar, significa que <code>notificationsCreate</code> não está sendo chamado.</p>
        <p class="text-zinc-500">Se mostrar ✓ até o final mas o push não chegou, o problema é no navegador/service worker.</p>
    </div>
</div>

<?php
include __DIR__ . '/../../views/partials/admin_layout_end.php';
include __DIR__ . '/../../views/partials/footer.php';
?>
