<?php
declare(strict_types=1);
/**
 * Public blog category page — Lists all posts within a specific category
 */
if (session_status() !== PHP_SESSION_ACTIVE) session_start();

require_once __DIR__ . '/../src/db.php';
require_once __DIR__ . '/../src/blog.php';
require_once __DIR__ . '/../src/storefront.php';
require_once __DIR__ . '/../src/media.php';

// Helper to resolve blog image (supports media:ID and legacy filesystem)
function blogCatImgSrc(?string $raw): string {
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
    if (!blogIsEnabled($conn)) { header('Location: /'); exit; }
    $visRole = $isLoggedIn ? ($userRole ?: 'usuario') : 'public';
    if (!blogIsVisibleForRole($conn, $visRole)) { header('Location: /'); exit; }
} catch (\Throwable $e) {
    $visRole = 'public';
}

$catSlug = trim((string)($_GET['cat_slug'] ?? ''));
if ($catSlug === '') { header('Location: /blog'); exit; }

// Decode slug back to category name (slugs use hyphens, names use spaces/original chars)
// Try exact match first, then slug-match
try {
    $allCats = blogGetCategories($conn);
} catch (\Throwable $e) {
    $allCats = [];
}

$categoryName = '';
foreach ($allCats as $c) {
    // blogGenerateSlug is available from src/blog.php
    if (blogGenerateSlug($c) === $catSlug || mb_strtolower($c) === mb_strtolower($catSlug)) {
        $categoryName = $c;
        break;
    }
}

if ($categoryName === '') {
    // Try URL-decoded version
    $catDecoded = urldecode($catSlug);
    foreach ($allCats as $c) {
        if (mb_strtolower($c) === mb_strtolower($catDecoded)) {
            $categoryName = $c;
            break;
        }
    }
}

if ($categoryName === '') {
    header('Location: /blog');
    exit;
}

// Fetch posts
$search = trim((string)($_GET['q'] ?? ''));
$page   = max(1, (int)($_GET['page'] ?? 1));
$limit  = 12;
$offset = ($page - 1) * $limit;

try {
    $posts = blogListFiltered($conn, 'publicado', $limit, $offset, $search, $categoryName);
    $total = blogCountFiltered($conn, 'publicado', $search, $categoryName);
} catch (\Throwable $e) {
    $posts = [];
    $total = 0;
}
$pages = (int)ceil(max($total, 1) / $limit);

// Build URL helper
$catSlugSafe = blogGenerateSlug($categoryName);
function blogCatUrl(string $catSlug, int $pg = 1, string $q = ''): string {
    $params = [];
    if ($pg > 1) $params['page'] = $pg;
    if ($q !== '') $params['q'] = $q;
    return '/blog/categoria/' . urlencode($catSlug) . ($params ? '?' . http_build_query($params) : '');
}

$cartCount   = function_exists('sfCartCount') ? sfCartCount() : 0;
$currentPage = 'blog';
$pageTitle   = $categoryName . ' — Blog';
include __DIR__ . '/../views/partials/header.php';
include __DIR__ . '/../views/partials/storefront_nav.php';
?>

<div class="min-h-screen bg-blackx">
    <div class="max-w-[1600px] mx-auto px-4 sm:px-6 pt-8 pb-12">

        <!-- Breadcrumb -->
        <nav class="flex items-center gap-2 text-sm text-zinc-500 mb-6 animate-fade-in">
            <a href="/" class="hover:text-greenx transition-colors">Início</a>
            <i data-lucide="chevron-right" class="w-3.5 h-3.5"></i>
            <a href="/blog" class="hover:text-greenx transition-colors">Blog</a>
            <i data-lucide="chevron-right" class="w-3.5 h-3.5"></i>
            <span class="text-zinc-300"><?= htmlspecialchars($categoryName, ENT_QUOTES, 'UTF-8') ?></span>
        </nav>

        <!-- Category Header -->
        <div class="mb-8 animate-fade-in-up">
            <div class="flex items-center gap-3 mb-3">
                <div class="w-12 h-12 rounded-2xl bg-greenx/10 border border-greenx/20 flex items-center justify-center">
                    <i data-lucide="tag" class="w-5 h-5 text-greenx"></i>
                </div>
                <div>
                    <h1 class="text-2xl sm:text-3xl font-black tracking-tight"><?= htmlspecialchars($categoryName, ENT_QUOTES, 'UTF-8') ?></h1>
                    <p class="text-sm text-zinc-400"><?= $total ?> post<?= $total !== 1 ? 's' : '' ?> nesta categoria</p>
                </div>
            </div>
        </div>

        <!-- Search -->
        <form method="get" action="/blog/categoria/<?= htmlspecialchars($catSlugSafe, ENT_QUOTES, 'UTF-8') ?>" class="max-w-md mb-8 animate-fade-in-up stagger-1">
            <div class="relative group">
                <div class="absolute inset-0 rounded-2xl bg-gradient-to-r from-greenx/20 to-greenxd/20 blur-xl opacity-0 group-focus-within:opacity-100 transition-opacity duration-500"></div>
                <div class="relative flex items-center bg-blackx2 border border-white/[0.08] rounded-2xl overflow-hidden group-focus-within:border-greenx/30 transition-all duration-300 shadow-lg shadow-black/20">
                    <i data-lucide="search" class="w-5 h-5 text-zinc-500 ml-4 shrink-0"></i>
                    <input type="text" name="q" value="<?= htmlspecialchars($search, ENT_QUOTES, 'UTF-8') ?>"
                           placeholder="Pesquisar em <?= htmlspecialchars($categoryName, ENT_QUOTES, 'UTF-8') ?>..."
                           class="flex-1 bg-transparent px-3 py-3 text-sm focus:outline-none placeholder:text-zinc-600">
                    <?php if ($search !== ''): ?>
                    <a href="/blog/categoria/<?= htmlspecialchars($catSlugSafe, ENT_QUOTES, 'UTF-8') ?>" class="p-2 text-zinc-500 hover:text-zinc-300 transition-colors">
                        <i data-lucide="x" class="w-4 h-4"></i>
                    </a>
                    <?php endif; ?>
                    <button type="submit" class="bg-gradient-to-r from-greenx to-greenxd text-white font-bold text-sm px-4 py-2 mr-1.5 rounded-xl hover:shadow-lg hover:shadow-greenx/20 transition-all">
                        Buscar
                    </button>
                </div>
            </div>
        </form>

        <!-- All categories pill row -->
        <?php if (!empty($allCats)): ?>
        <div class="flex flex-wrap items-center gap-2 mb-8 animate-fade-in-up stagger-2">
            <a href="/blog"
               class="px-4 py-1.5 rounded-full text-xs font-semibold border border-white/[0.08] text-zinc-400 hover:border-greenx/30 hover:text-greenx bg-blackx2 transition-all duration-200">
                Todos
            </a>
            <?php foreach ($allCats as $cat):
                $isActive = ($cat === $categoryName);
                $slug = blogGenerateSlug($cat);
            ?>
            <a href="/blog/categoria/<?= htmlspecialchars($slug, ENT_QUOTES, 'UTF-8') ?>"
               class="px-4 py-1.5 rounded-full text-xs font-semibold border transition-all duration-200 <?= $isActive ? 'bg-greenx text-white border-greenx shadow-lg shadow-greenx/20' : 'border-white/[0.08] text-zinc-400 hover:border-greenx/30 hover:text-greenx bg-blackx2' ?>">
                <?= htmlspecialchars($cat, ENT_QUOTES, 'UTF-8') ?>
            </a>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <?php if (empty($posts)): ?>
        <div class="text-center py-20">
            <div class="w-20 h-20 mx-auto rounded-2xl bg-white/[0.03] border border-white/[0.06] flex items-center justify-center mb-4">
                <i data-lucide="file-text" class="w-8 h-8 text-zinc-600"></i>
            </div>
            <p class="text-zinc-400 font-medium mb-1">Nenhum post encontrado</p>
            <p class="text-zinc-600 text-sm">Tente outra busca ou volte ao blog</p>
            <a href="/blog" class="inline-flex items-center gap-2 mt-4 text-sm text-greenx hover:underline">
                <i data-lucide="arrow-left" class="w-3.5 h-3.5"></i> Ver todos os posts
            </a>
        </div>
        <?php else: ?>
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
            <?php foreach ($posts as $i => $post): ?>
            <article class="product-card group bg-blackx2 border border-white/[0.06] rounded-2xl overflow-hidden hover:border-greenx/15 transition-all duration-300 animate-fade-in-up stagger-<?= ($i % 8) + 1 ?>">
                <a href="/blog/<?= htmlspecialchars((string)$post['slug']) ?>" class="block relative">
                    <?php
                      $blogImgUrl = blogCatImgSrc((string)($post['imagem'] ?? ''));
                    ?>
                    <?php if ($blogImgUrl !== ''): ?>
                    <div class="aspect-[16/9] overflow-hidden bg-blackx">
                        <img src="<?= htmlspecialchars($blogImgUrl) ?>"
                             alt="<?= htmlspecialchars((string)$post['titulo']) ?>"
                             class="w-full h-full object-cover group-hover:scale-[1.03] transition-transform duration-500" loading="lazy">
                    </div>
                    <?php else: ?>
                    <div class="aspect-[16/9] bg-gradient-to-br from-greenx/10 via-greenx/5 to-transparent flex items-center justify-center">
                        <i data-lucide="file-text" class="w-10 h-10 text-greenx/20"></i>
                    </div>
                    <?php endif; ?>
                    <span class="absolute top-3 left-3 px-2.5 py-0.5 rounded-full bg-black/60 backdrop-blur-sm border border-white/10 text-[10px] font-bold uppercase tracking-wider text-white/80">
                        <?= htmlspecialchars($categoryName, ENT_QUOTES, 'UTF-8') ?>
                    </span>
                </a>
                <div class="p-5 space-y-3">
                    <div class="flex items-center gap-2 text-xs text-zinc-500">
                        <a href="/blog/autor/<?= (int)$post['author_id'] ?>" class="hover:text-greenx transition-colors"><?= htmlspecialchars((string)$post['author_nome']) ?></a>
                        <span>&middot;</span>
                        <span><?= date('d/m/Y', strtotime((string)$post['criado_em'])) ?></span>
                        <?php if ((int)($post['visualizacoes'] ?? 0) > 0): ?>
                        <span>&middot;</span>
                        <span><i data-lucide="eye" class="w-3 h-3 inline -mt-0.5"></i> <?= number_format((int)$post['visualizacoes']) ?></span>
                        <?php endif; ?>
                    </div>
                    <a href="/blog/<?= htmlspecialchars((string)$post['slug']) ?>"
                       class="block font-bold text-lg leading-tight group-hover:text-greenx transition-colors line-clamp-2">
                        <?= htmlspecialchars((string)$post['titulo']) ?>
                    </a>
                    <?php if (!empty($post['resumo'])): ?>
                    <p class="text-sm text-zinc-400 line-clamp-2 leading-relaxed"><?= htmlspecialchars((string)$post['resumo']) ?></p>
                    <?php endif; ?>
                    <div class="pt-1">
                        <span class="inline-flex items-center gap-1 text-sm text-greenx font-semibold group-hover:gap-2 transition-all">
                            Ler mais <i data-lucide="arrow-right" class="w-3.5 h-3.5"></i>
                        </span>
                    </div>
                </div>
            </article>
            <?php endforeach; ?>
        </div>

        <!-- Pagination -->
        <?php if ($pages > 1): ?>
        <nav class="flex items-center justify-center gap-2 mt-10" aria-label="Paginação">
            <?php if ($page > 1): ?>
            <a href="<?= blogCatUrl($catSlugSafe, $page - 1, $search) ?>"
               class="flex items-center gap-1 px-3 py-2 rounded-xl text-sm font-medium border border-white/[0.08] text-zinc-400 hover:border-greenx/30 hover:text-greenx transition-all">
                <i data-lucide="chevron-left" class="w-4 h-4"></i> Anterior
            </a>
            <?php endif; ?>

            <?php
            $start = max(1, $page - 2);
            $end   = min($pages, $page + 2);
            if ($start > 1): ?>
            <a href="<?= blogCatUrl($catSlugSafe, 1, $search) ?>"
               class="w-10 h-10 flex items-center justify-center rounded-xl text-sm font-semibold border border-white/[0.08] text-zinc-400 hover:border-greenx/30 hover:text-greenx transition-all">1</a>
            <?php if ($start > 2): ?><span class="text-zinc-600 px-1">...</span><?php endif; ?>
            <?php endif; ?>

            <?php for ($p = $start; $p <= $end; $p++): ?>
            <a href="<?= blogCatUrl($catSlugSafe, $p, $search) ?>"
               class="w-10 h-10 flex items-center justify-center rounded-xl text-sm font-semibold border transition-all <?= $p === $page ? 'bg-greenx text-white border-greenx shadow-lg shadow-greenx/20' : 'border-white/[0.08] text-zinc-400 hover:border-greenx/30 hover:text-greenx' ?>">
                <?= $p ?>
            </a>
            <?php endfor; ?>

            <?php if ($end < $pages): ?>
            <?php if ($end < $pages - 1): ?><span class="text-zinc-600 px-1">...</span><?php endif; ?>
            <a href="<?= blogCatUrl($catSlugSafe, $pages, $search) ?>"
               class="w-10 h-10 flex items-center justify-center rounded-xl text-sm font-semibold border border-white/[0.08] text-zinc-400 hover:border-greenx/30 hover:text-greenx transition-all"><?= $pages ?></a>
            <?php endif; ?>

            <?php if ($page < $pages): ?>
            <a href="<?= blogCatUrl($catSlugSafe, $page + 1, $search) ?>"
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
