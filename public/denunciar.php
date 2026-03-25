<?php
declare(strict_types=1);
/**
 * Denunciar (Report) page — storefront
 */
if (session_status() !== PHP_SESSION_ACTIVE) session_start();

require_once __DIR__ . '/../src/db.php';
require_once __DIR__ . '/../src/storefront.php';
require_once __DIR__ . '/../src/reports.php';

$userId     = (int)($_SESSION['user_id'] ?? 0);
$isLoggedIn = $userId > 0;

$conn = (new Database())->connect();

$productId = (int)($_GET['produto_id'] ?? $_POST['produto_id'] ?? 0);

// Load product info for sidebar
$produto = null;
if ($productId > 0) {
    $produto = sfGetProductById($conn, $productId);
}

$erro = '';
$sucesso = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $isLoggedIn) {
    $motivo   = trim((string)($_POST['motivo'] ?? ''));
    $mensagem = trim((string)($_POST['mensagem'] ?? ''));
    $postProdId = (int)($_POST['produto_id'] ?? 0);

    [$ok, $msg] = reportSubmit($conn, $postProdId, $userId, $motivo, $mensagem);
    if ($ok) {
        $sucesso = $msg;
    } else {
        $erro = $msg;
    }
}

$motivos = [
    'Tem contato externo. (Ex: Discord, Instagram, Telefone, etc).',
    'Está na categoria incorreta.',
    'Está tentando burlar classificação do anúncio.',
    'O produto/serviço é ilegal ou não cumpre com as nossas políticas de produtos proibidos.',
    'Está oferecendo pagamento por fora da plataforma.',
    'As imagens são falsas, editadas ou que ferem nossas políticas.',
    'É uma tentativa de fraude ou roubo.',
    'Tem conteúdo ofensivo, obsceno ou discriminatório.',
    'Outros',
];

$cartCount   = function_exists('sfCartCount') ? sfCartCount() : 0;
$currentPage = '';
$pageTitle   = 'Denunciar';
include __DIR__ . '/../views/partials/header.php';
include __DIR__ . '/../views/partials/storefront_nav.php';
?>

<div class="min-h-screen bg-blackx">
    <div class="max-w-[1200px] mx-auto px-4 sm:px-6 py-8">
        <!-- Breadcrumb -->
        <nav class="flex items-center gap-2 text-sm text-zinc-500 mb-6">
            <a href="/" class="hover:text-greenx transition-colors">Início</a>
            <span>&rsaquo;</span>
            <span class="text-zinc-300">Denunciar</span>
        </nav>

        <h1 class="text-3xl font-black text-center mb-10">Denunciar</h1>

        <?php if ($sucesso): ?>
        <div class="max-w-2xl mx-auto mb-6 rounded-xl bg-greenx/10 border border-greenx/30 text-greenx px-5 py-4 text-sm flex items-center gap-3">
            <i data-lucide="check-circle" class="w-5 h-5 flex-shrink-0"></i>
            <?= htmlspecialchars($sucesso) ?>
        </div>
        <?php endif; ?>

        <?php if ($erro): ?>
        <div class="max-w-2xl mx-auto mb-6 rounded-xl bg-red-500/10 border border-red-500/30 text-red-300 px-5 py-4 text-sm flex items-center gap-3">
            <i data-lucide="alert-circle" class="w-5 h-5 flex-shrink-0"></i>
            <?= htmlspecialchars($erro) ?>
        </div>
        <?php endif; ?>

        <?php if (!$isLoggedIn): ?>
        <div class="max-w-2xl mx-auto text-center py-12">
            <i data-lucide="shield-alert" class="w-12 h-12 text-zinc-600 mx-auto mb-4"></i>
            <p class="text-zinc-400 mb-4">Você precisa estar logado para enviar uma denúncia.</p>
            <a href="/login" class="inline-flex items-center gap-2 px-6 py-3 rounded-xl bg-greenx text-white font-bold text-sm hover:bg-greenx2 transition-all">
                <i data-lucide="log-in" class="w-4 h-4"></i> Fazer login
            </a>
        </div>
        <?php else: ?>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            <!-- Left: Form -->
            <div class="lg:col-span-2">
                <form method="post">
                    <input type="hidden" name="produto_id" value="<?= $productId ?>">

                    <div class="mb-8">
                        <h2 class="text-lg font-bold mb-4">Por que você quer denunciar o anúncio?</h2>
                        <div class="space-y-0 border border-white/[0.08] rounded-xl overflow-hidden">
                            <?php foreach ($motivos as $i => $m): ?>
                            <label class="flex items-center gap-3 px-4 py-3.5 cursor-pointer hover:bg-white/[0.03] transition-colors <?= $i < count($motivos) - 1 ? 'border-b border-white/[0.06]' : '' ?>">
                                <input type="radio" name="motivo" value="<?= htmlspecialchars($m) ?>" required
                                       class="w-4 h-4 text-greenx bg-white/[0.04] border-white/[0.15] focus:ring-greenx/30 focus:ring-offset-0 accent-[var(--t-accent)]">
                                <span class="text-sm text-zinc-300"><?= htmlspecialchars($m) ?></span>
                            </label>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <div class="mb-8">
                        <h2 class="text-lg font-bold mb-3">Conte mais sobre a sua denúncia</h2>
                        <textarea name="mensagem" rows="5" maxlength="2000"
                                  placeholder="Descreva com detalhes informações e o motivo da sua denúncia. Se necessário, hospede um álbum de imagens (https://imgur.com/) e provas contundentes que fortaleçam sua denúncia. Reportes falsos ou repetidos poderão ocasionar banimento."
                                  class="w-full rounded-xl bg-white/[0.03] border border-white/[0.08] px-4 py-3 text-sm focus:border-greenx/50 focus:outline-none transition-colors resize-none placeholder:text-zinc-600"></textarea>
                    </div>

                    <div class="text-center">
                        <button type="submit"
                                class="inline-flex items-center gap-2 px-8 py-3 rounded-xl bg-red-600 hover:bg-red-500 text-white font-bold text-sm transition-all shadow-lg shadow-red-600/20">
                            Enviar denúncia
                        </button>
                    </div>
                </form>
            </div>

            <!-- Right: Product details sidebar -->
            <?php if ($produto): ?>
            <div class="lg:col-span-1">
                <div class="bg-blackx2 border border-white/[0.06] rounded-2xl p-5 sticky top-24">
                    <h3 class="text-sm font-bold text-center mb-4 uppercase tracking-wide">Detalhes do anúncio</h3>
                    <div class="flex items-start gap-3">
                        <div class="w-16 h-16 rounded-xl overflow-hidden border border-white/[0.08] flex-shrink-0 bg-blackx">
                            <img src="<?= htmlspecialchars(sfImageUrl((string)($produto['imagem'] ?? '')), ENT_QUOTES, 'UTF-8') ?>"
                                 alt="" class="w-full h-full object-cover" loading="lazy">
                        </div>
                        <div class="flex-1 min-w-0">
                            <a href="<?= sfProductUrl($produto) ?>" class="font-bold text-sm leading-tight hover:text-greenx transition-colors line-clamp-3 uppercase">
                                <?= htmlspecialchars((string)($produto['nome'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <?php endif; ?>
    </div>
</div>

<?php
include __DIR__ . '/../views/partials/storefront_footer.php';
include __DIR__ . '/../views/partials/footer.php';
?>
