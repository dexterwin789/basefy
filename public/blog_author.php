<?php
declare(strict_types=1);
/**
 * Blog posts by a specific author
 */
if (session_status() !== PHP_SESSION_ACTIVE) session_start();

require_once __DIR__ . '/../src/db.php';
require_once __DIR__ . '/../src/blog.php';
require_once __DIR__ . '/../src/storefront.php';

$conn = (new Database())->connect();

$userId   = (int)($_SESSION['user_id'] ?? 0);
$userRole = (string)($_SESSION['user']['role'] ?? '');
$isLoggedIn = $userId > 0;

try {
    if (!blogIsEnabled($conn)) { header('Location: /'); exit; }
    $visRole = $isLoggedIn ? ($userRole ?: 'usuario') : 'public';
    if (!blogIsVisibleForRole($conn, $visRole)) { header('Location: /'); exit; }
} catch (\Throwable $e) {}

$authorId = (int)($_GET['author_id'] ?? 0);
if ($authorId <= 0) { header('Location: /blog'); exit; }

$author = blogGetAuthor($conn, $authorId);
if (!$author) { header('Location: /blog'); exit; }

$page   = max(1, (int)($_GET['page'] ?? 1));
$limit  = 12;
$offset = ($page - 1) * $limit;

try {
    $posts = blogListByAuthor($conn, $authorId, $limit, $offset);
    $total = blogCountByAuthor($conn, $authorId);
} catch (\Throwable $e) {
    $posts = [];
    $total = 0;
}
$pages = (int)ceil(max($total, 1) / $limit);

$cartCount   = function_exists('sfCartCount') ? sfCartCount() : 0;
$currentPage = 'blog';
$pageTitle   = 'Posts de ' . ($author['nome'] ?? 'Autor') . ' — Blog';
include __DIR__ . '/../views/partials/header.php';
include __DIR__ . '/../views/partials/storefront_nav.php';
?>

<div class="min-h-screen bg-blackx">
    <div class="max-w-[1600px] mx-auto px-4 sm:px-6 py-10">
        <!-- Header -->
        <div class="animate-fade-in mb-10">
            <div class="flex items-center gap-2 text-sm text-zinc-500 mb-4">
                <a href="<?= BASE_PATH ?>/" class="hover:text-greenx transition-colors">Início</a>
                <i data-lucide="chevron-right" class="w-3.5 h-3.5"></i>
                <a href="<?= BASE_PATH ?>/blog" class="hover:text-greenx transition-colors">Blog</a>
                <i data-lucide="chevron-right" class="w-3.5 h-3.5"></i>
                <span class="text-white font-medium"><?= htmlspecialchars((string)($author['nome'] ?? 'Autor'), ENT_QUOTES, 'UTF-8') ?></span>
            </div>
            <div class="flex items-center gap-4">
                <div class="w-14 h-14 rounded-full bg-gradient-to-br from-greenx/30 to-greenxd/20 border-2 border-greenx/30 flex items-center justify-center">
                    <span class="text-xl font-bold text-greenx"><?= strtoupper(mb_substr((string)($author['nome'] ?? 'A'), 0, 1)) ?></span>
                </div>
                <div>
                    <h1 class="text-2xl sm:text-3xl font-black tracking-tight"><?= htmlspecialchars((string)($author['nome'] ?? 'Autor'), ENT_QUOTES, 'UTF-8') ?></h1>
                    <p class="text-zinc-400 text-sm mt-0.5"><?= $total ?> post<?= $total !== 1 ? 's' : '' ?> publicado<?= $total !== 1 ? 's' : '' ?></p>
                </div>
            </div>
        </div>

        <?php if (empty($posts)): ?>
        <div class="text-center py-16">
            <i data-lucide="file-text" class="w-16 h-16 mx-auto text-zinc-600 mb-4"></i>
            <p class="text-zinc-400">Este autor ainda não publicou nenhum post.</p>
            <a href="<?= BASE_PATH ?>/blog" class="inline-flex items-center gap-2 mt-4 text-sm text-greenx hover:underline">
                <i data-lucide="arrow-left" class="w-3.5 h-3.5"></i> Voltar ao blog
            </a>
        </div>
        <?php else: ?>
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
            <?php foreach ($posts as $i => $post): ?>
            <article class="product-card group bg-blackx2 border border-white/[0.06] rounded-2xl overflow-hidden animate-fade-in-up stagger-<?= ($i % 8) + 1 ?>">
                <a href="/blog/<?= htmlspecialchars((string)$post['slug']) ?>" class="block">
                    <?php if (!empty($post['imagem'])): ?>
                    <div class="aspect-[16/9] overflow-hidden bg-blackx">
                        <img src="<?= BASE_PATH ?>/uploads/<?= htmlspecialchars((string)$post['imagem']) ?>"
                             alt="<?= htmlspecialchars((string)$post['titulo']) ?>"
                             class="w-full h-full object-cover" loading="lazy">
                    </div>
                    <?php else: ?>
                    <div class="aspect-[16/9] bg-gradient-to-br from-greenx/10 to-transparent flex items-center justify-center">
                        <i data-lucide="file-text" class="w-12 h-12 text-greenx/30"></i>
                    </div>
                    <?php endif; ?>
                </a>
                <div class="p-5 space-y-3">
                    <div class="flex items-center gap-2 text-xs text-zinc-500">
                        <span><?= date('d/m/Y', strtotime((string)$post['criado_em'])) ?></span>
                        <?php if ((int)$post['visualizacoes'] > 0): ?>
                        <span>&middot;</span>
                        <span><?= number_format((int)$post['visualizacoes']) ?> views</span>
                        <?php endif; ?>
                    </div>
                    <a href="/blog/<?= htmlspecialchars((string)$post['slug']) ?>"
                       class="block font-bold text-lg leading-tight hover:text-greenx transition-colors line-clamp-2">
                        <?= htmlspecialchars((string)$post['titulo']) ?>
                    </a>
                    <?php if (!empty($post['resumo'])): ?>
                    <p class="text-sm text-zinc-400 line-clamp-2 leading-relaxed"><?= htmlspecialchars((string)$post['resumo']) ?></p>
                    <?php endif; ?>
                    <a href="/blog/<?= htmlspecialchars((string)$post['slug']) ?>"
                       class="inline-flex items-center gap-1 text-sm text-greenx font-semibold hover:underline">
                        Ler mais <i data-lucide="arrow-right" class="w-3.5 h-3.5"></i>
                    </a>
                </div>
            </article>
            <?php endforeach; ?>
        </div>

        <!-- Pagination -->
        <?php if ($pages > 1): ?>
        <div class="flex justify-center gap-2 mt-10">
            <?php for ($p = 1; $p <= $pages; $p++): ?>
            <a href="?author_id=<?= $authorId ?>&page=<?= $p ?>"
               class="px-4 py-2 rounded-xl text-sm font-semibold border transition-all <?= $p === $page ? 'bg-greenx text-white border-greenx' : 'border-white/[0.08] text-zinc-400 hover:border-greenx/30 hover:text-greenx' ?>">
                <?= $p ?>
            </a>
            <?php endfor; ?>
        </div>
        <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<?php
include __DIR__ . '/../views/partials/storefront_footer.php';
include __DIR__ . '/../views/partials/footer.php';
?>
