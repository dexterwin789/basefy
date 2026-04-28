<?php
declare(strict_types=1);
/**
 * Public blog listing page — Premium design with search, categories, pagination
 */
if (session_status() !== PHP_SESSION_ACTIVE) session_start();

require_once __DIR__ . '/../src/db.php';
require_once __DIR__ . '/../src/blog.php';
require_once __DIR__ . '/../src/storefront.php';
require_once __DIR__ . '/../src/media.php';

// Helper to resolve blog image (supports media:ID and legacy filesystem)
function blogImgSrc(?string $raw): string {
    if (!$raw || trim($raw) === '') return '';
    if (str_starts_with($raw, 'media:')) return mediaResolveUrl($raw);
    if (preg_match('#^https?://#i', $raw)) return $raw;
    return (defined('BASE_PATH') ? BASE_PATH : '') . '/uploads/' . $raw;
}

$conn = (new Database())->connect();

// Check visibility
$userId   = (int)($_SESSION['user_id'] ?? 0);
$userRole = (string)($_SESSION['user']['role'] ?? '');
$isLoggedIn = $userId > 0;

try {
    if (!blogIsEnabled($conn)) {
        header('Location: /');
        exit;
    }
    $visRole = $isLoggedIn ? ($userRole ?: 'usuario') : 'public';
    if (!blogIsVisibleForRole($conn, $visRole)) {
        header('Location: /');
        exit;
    }
} catch (\Throwable $e) {
    $visRole = 'public';
}

// Filters
$search    = trim((string)($_GET['q'] ?? ''));
$catFilter = trim((string)($_GET['categoria'] ?? ''));
$page      = max(1, (int)($_GET['page'] ?? 1));
$limit     = 12;
$offset    = ($page - 1) * $limit;

try {
    $categories = blogGetCategories($conn);
} catch (\Throwable $e) {
    $categories = [];
}

try {
    $posts = blogListFiltered($conn, 'publicado', $limit, $offset, $search, $catFilter);
    $total = blogCountFiltered($conn, 'publicado', $search, $catFilter);
} catch (\Throwable $e) {
    $posts = [];
    $total = 0;
}
$pages = (int)ceil(max($total, 1) / $limit);

// Featured post (first post on page 1 with no filters)
$featured = null;
if ($page === 1 && $search === '' && $catFilter === '' && count($posts) > 0) {
    $featured = array_shift($posts);
}

// Build URL helper
function blogFilterUrl(int $pg = 1, string $q = '', string $cat = ''): string {
    $params = [];
    if ($pg > 1) $params['page'] = $pg;
    if ($q !== '') $params['q'] = $q;
    if ($cat !== '') $params['categoria'] = $cat;
    return '/blog' . ($params ? '?' . http_build_query($params) : '');
}

$cartCount   = function_exists('sfCartCount') ? sfCartCount() : 0;
$currentPage = 'blog';
$pageTitle   = 'Blog — ' . (defined('APP_NAME') ? APP_NAME : 'Marketplace');
include __DIR__ . '/../views/partials/header.php';
include __DIR__ . '/../views/partials/storefront_nav.php';
?>

<div class="min-h-screen bg-blackx">
    <!-- Hero header — matches categorias.php style -->
    <div class="relative overflow-hidden">
        <div class="absolute inset-0 bg-gradient-to-b from-greenx/[0.04] via-transparent to-transparent pointer-events-none"></div>
        <div class="max-w-[1440px] mx-auto px-4 sm:px-6 pt-10 pb-6 relative z-10">
            <div class="text-center mb-8 animate-fade-in">
                <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-greenx/10 border border-greenx/20 text-greenx text-xs font-semibold mb-4">
                    <i data-lucide="newspaper" class="w-3.5 h-3.5"></i> Blog
                </div>
                <h1 class="text-3xl sm:text-4xl lg:text-5xl font-black tracking-tight mb-3">
                    Novidades & <span class="bg-gradient-to-r from-greenx to-greenxd bg-clip-text text-transparent">Dicas</span>
                </h1>
                <p class="text-zinc-400 max-w-lg mx-auto text-sm sm:text-base">Fique por dentro das últimas novidades, tutoriais e atualizações</p>
            </div>

            <!-- Search Bar -->
            <form method="get" action="/blog" class="max-w-xl mx-auto mb-6 animate-fade-in-up stagger-1">
                <?php if ($catFilter !== ''): ?>
                <input type="hidden" name="categoria" value="<?= htmlspecialchars($catFilter, ENT_QUOTES, 'UTF-8') ?>">
                <?php endif; ?>
                <div class="relative group">
                    <div class="absolute inset-0 rounded-2xl bg-gradient-to-r from-greenx/20 to-greenxd/20 blur-xl opacity-0 group-focus-within:opacity-100 transition-opacity duration-500"></div>
                    <div class="relative flex items-center bg-blackx2 border border-white/[0.08] rounded-2xl overflow-hidden group-focus-within:border-greenx/30 transition-all duration-300 shadow-lg shadow-black/20">
                        <i data-lucide="search" class="w-5 h-5 text-zinc-500 ml-4 shrink-0"></i>
                        <input type="text" name="q" value="<?= htmlspecialchars($search, ENT_QUOTES, 'UTF-8') ?>"
                               placeholder="Pesquisar artigos..."
                               class="flex-1 bg-transparent px-3 py-3.5 text-sm focus:outline-none placeholder:text-zinc-600">
                        <?php if ($search !== ''): ?>
                        <a href="<?= blogFilterUrl(1, '', $catFilter) ?>" class="p-2 text-zinc-500 hover:text-zinc-300 transition-colors">
                            <i data-lucide="x" class="w-4 h-4"></i>
                        </a>
                        <?php endif; ?>
                        <button type="submit" class="bg-gradient-to-r from-greenx to-greenxd text-white font-bold text-sm px-5 py-2.5 mr-1.5 rounded-xl hover:shadow-lg hover:shadow-greenx/20 transition-all">
                            Buscar
                        </button>
                    </div>
                </div>
            </form>

            <!-- Category Pills -->
            <?php if (!empty($categories)): ?>
            <div class="flex flex-wrap items-center justify-center gap-2 animate-fade-in-up stagger-2">
                <a href="/blog"
                   class="px-4 py-1.5 rounded-full text-xs font-semibold border transition-all duration-200 <?= $catFilter === '' ? 'bg-greenx text-white border-greenx shadow-lg shadow-greenx/20' : 'border-white/[0.08] text-zinc-400 hover:border-greenx/30 hover:text-greenx bg-blackx2' ?>">
                    Todos
                </a>
                <?php foreach ($categories as $cat): ?>
                <a href="/blog/categoria/<?= htmlspecialchars(blogGenerateSlug($cat), ENT_QUOTES, 'UTF-8') ?>"
                   class="px-4 py-1.5 rounded-full text-xs font-semibold border transition-all duration-200 <?= $catFilter === $cat ? 'bg-greenx text-white border-greenx shadow-lg shadow-greenx/20' : 'border-white/[0.08] text-zinc-400 hover:border-greenx/30 hover:text-greenx bg-blackx2' ?>">
                    <?= htmlspecialchars($cat, ENT_QUOTES, 'UTF-8') ?>
                </a>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="max-w-[1440px] mx-auto px-4 sm:px-6 pb-12">

        <!-- Active filters -->
        <?php if ($search !== '' || $catFilter !== ''): ?>
        <div class="flex items-center gap-3 mb-6 text-sm text-zinc-400">
            <span><?= $total ?> resultado<?= $total !== 1 ? 's' : '' ?></span>
            <?php if ($search !== ''): ?>
            <span class="flex items-center gap-1 bg-white/[0.04] border border-white/[0.08] rounded-lg px-2.5 py-1">
                "<?= htmlspecialchars($search, ENT_QUOTES, 'UTF-8') ?>"
                <a href="<?= blogFilterUrl(1, '', $catFilter) ?>" class="text-zinc-500 hover:text-red-400 transition-colors"><i data-lucide="x" class="w-3.5 h-3.5"></i></a>
            </span>
            <?php endif; ?>
            <?php if ($catFilter !== ''): ?>
            <span class="flex items-center gap-1 bg-greenx/10 border border-greenx/20 rounded-lg px-2.5 py-1 text-greenx">
                <?= htmlspecialchars($catFilter, ENT_QUOTES, 'UTF-8') ?>
                <a href="<?= blogFilterUrl(1, $search, '') ?>" class="text-greenx/50 hover:text-red-400 transition-colors"><i data-lucide="x" class="w-3.5 h-3.5"></i></a>
            </span>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <?php
        // Merge featured back for a uniform grid
        $allPosts = [];
        if ($featured) { $allPosts[] = $featured; }
        $allPosts = array_merge($allPosts, $posts);
        ?>

        <?php if (empty($allPosts)): ?>
        <div class="rounded-3xl border border-white/[0.06] bg-white/[0.02] p-16 text-center animate-fade-in-up">
            <div class="w-16 h-16 rounded-2xl bg-white/[0.04] border border-white/[0.06] flex items-center justify-center mx-auto mb-4">
                <i data-lucide="file-text" class="w-7 h-7 text-zinc-600"></i>
            </div>
            <h3 class="text-lg font-semibold text-zinc-300">Nenhum post encontrado</h3>
            <p class="text-sm text-zinc-500 mt-2 max-w-sm mx-auto">Tente outra busca ou categoria</p>
            <?php if ($search !== '' || $catFilter !== ''): ?>
            <a href="/blog" class="inline-flex items-center gap-2 mt-5 rounded-xl bg-white/[0.06] border border-white/[0.08] px-5 py-2.5 text-sm text-zinc-300 hover:text-white hover:border-white/[0.15] transition-all">
                <i data-lucide="rotate-ccw" class="w-3.5 h-3.5"></i> Limpar filtros
            </a>
            <?php endif; ?>
        </div>
        <?php else: ?>

        <!-- Blog grid — same layout as categorias.php product cards -->
        <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-4 sm:gap-5">
            <?php foreach ($allPosts as $i => $post): ?>
            <article class="product-card group bg-blackx2 border border-white/[0.06] rounded-2xl overflow-hidden flex flex-col animate-fade-in-up stagger-<?= min(($i % 8) + 1, 8) ?>">
                <a href="/blog/<?= htmlspecialchars((string)$post['slug']) ?>" class="block relative overflow-hidden">
                    <?php $imgSrc = blogImgSrc((string)($post['imagem'] ?? '')); ?>
                    <div class="aspect-[4/3] overflow-hidden bg-blackx">
                        <?php if ($imgSrc !== ''): ?>
                        <img src="<?= htmlspecialchars($imgSrc) ?>"
                             alt="<?= htmlspecialchars((string)$post['titulo']) ?>"
                             class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300" loading="lazy">
                        <?php else: ?>
                        <div class="w-full h-full bg-gradient-to-br from-greenx/10 via-greenx/5 to-transparent flex items-center justify-center">
                            <i data-lucide="newspaper" class="w-10 h-10 text-greenx/20"></i>
                        </div>
                        <?php endif; ?>
                    </div>
                    <?php if (!empty($post['categoria'])): ?>
                    <span class="absolute top-2 left-2 px-2 py-0.5 rounded-md text-[9px] font-bold uppercase tracking-wide bg-greenx/90 text-white backdrop-blur-sm">
                        <?= htmlspecialchars((string)$post['categoria']) ?>
                    </span>
                    <?php endif; ?>
                </a>
                <div class="p-3 flex flex-col flex-1 text-center">
                    <a href="/blog/<?= htmlspecialchars((string)$post['slug']) ?>"
                       class="font-semibold text-xs sm:text-sm line-clamp-2 min-h-[2rem] leading-snug hover:text-greenx transition-colors block">
                        <?= htmlspecialchars((string)$post['titulo']) ?>
                    </a>
                    <div class="flex-1"></div>
                    <div class="flex items-center justify-center gap-1.5 text-[10px] text-zinc-500 mt-1.5">
                        <i data-lucide="user" class="w-3 h-3 text-greenx shrink-0"></i>
                        <span class="truncate"><?= htmlspecialchars((string)$post['author_nome']) ?></span>
                    </div>
                    <div class="inline-flex items-center self-center gap-1.5 px-2.5 py-1 rounded-lg border border-white/[0.06] bg-white/[0.02] mt-1.5">
                        <i data-lucide="calendar" class="w-3 h-3 text-zinc-500"></i>
                        <span class="text-[10px] text-zinc-400"><?= date('d/m/Y', strtotime((string)$post['criado_em'])) ?></span>
                        <?php if ((int)($post['visualizacoes'] ?? 0) > 0): ?>
                        <span class="text-zinc-600">&middot;</span>
                        <i data-lucide="eye" class="w-3 h-3 text-zinc-500"></i>
                        <span class="text-[10px] text-zinc-400"><?= number_format((int)$post['visualizacoes']) ?></span>
                        <?php endif; ?>
                    </div>
                    <a href="/blog/<?= htmlspecialchars((string)$post['slug']) ?>"
                       class="w-full flex items-center justify-center gap-1.5 rounded-xl bg-gradient-to-r from-greenx to-greenxd hover:from-greenx2 hover:to-greenxd text-white font-bold px-3 py-2 text-xs shadow-lg shadow-greenx/10 hover:shadow-greenx/20 transition-all mt-1.5">
                        <i data-lucide="book-open" class="w-3 h-3"></i>
                        Ler mais
                    </a>
                </div>
            </article>
            <?php endforeach; ?>
        </div>

        <!-- Pagination -->
        <?php if ($pages > 1): ?>
        <nav class="flex items-center justify-center gap-2 mt-10" aria-label="Paginação">
            <?php if ($page > 1): ?>
            <a href="<?= blogFilterUrl($page - 1, $search, $catFilter) ?>"
               class="flex items-center gap-1 px-3 py-2 rounded-xl text-sm font-medium border border-white/[0.08] text-zinc-400 hover:border-greenx/30 hover:text-greenx transition-all">
                <i data-lucide="chevron-left" class="w-4 h-4"></i> Anterior
            </a>
            <?php endif; ?>

            <?php
            $start = max(1, $page - 2);
            $end   = min($pages, $page + 2);
            if ($start > 1): ?>
            <a href="<?= blogFilterUrl(1, $search, $catFilter) ?>"
               class="w-10 h-10 flex items-center justify-center rounded-xl text-sm font-semibold border border-white/[0.08] text-zinc-400 hover:border-greenx/30 hover:text-greenx transition-all">1</a>
            <?php if ($start > 2): ?><span class="text-zinc-600 px-1">...</span><?php endif; ?>
            <?php endif; ?>

            <?php for ($p = $start; $p <= $end; $p++): ?>
            <a href="<?= blogFilterUrl($p, $search, $catFilter) ?>"
               class="w-10 h-10 flex items-center justify-center rounded-xl text-sm font-semibold border transition-all <?= $p === $page ? 'bg-greenx text-white border-greenx shadow-lg shadow-greenx/20' : 'border-white/[0.08] text-zinc-400 hover:border-greenx/30 hover:text-greenx' ?>">
                <?= $p ?>
            </a>
            <?php endfor; ?>

            <?php if ($end < $pages): ?>
            <?php if ($end < $pages - 1): ?><span class="text-zinc-600 px-1">...</span><?php endif; ?>
            <a href="<?= blogFilterUrl($pages, $search, $catFilter) ?>"
               class="w-10 h-10 flex items-center justify-center rounded-xl text-sm font-semibold border border-white/[0.08] text-zinc-400 hover:border-greenx/30 hover:text-greenx transition-all"><?= $pages ?></a>
            <?php endif; ?>

            <?php if ($page < $pages): ?>
            <a href="<?= blogFilterUrl($page + 1, $search, $catFilter) ?>"
               class="flex items-center gap-1 px-3 py-2 rounded-xl text-sm font-medium border border-white/[0.08] text-zinc-400 hover:border-greenx/30 hover:text-greenx transition-all">
                Próximo <i data-lucide="chevron-right" class="w-4 h-4"></i>
            </a>
            <?php endif; ?>
        </nav>
        <?php endif; ?>

        <?php endif; ?>
    </div>
</div>

<?php
include __DIR__ . '/../views/partials/storefront_footer.php';
include __DIR__ . '/../views/partials/footer.php';
?>
