<?php
declare(strict_types=1);
/**
 * Public blog post detail page — Premium design
 */
if (session_status() !== PHP_SESSION_ACTIVE) session_start();

require_once __DIR__ . '/../src/db.php';
require_once __DIR__ . '/../src/blog.php';
require_once __DIR__ . '/../src/storefront.php';
require_once __DIR__ . '/../src/media.php';

// Helper to resolve blog image (supports media:ID and legacy filesystem)
function blogImgSrc2(?string $raw): string {
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
    header('Location: /');
    exit;
}

$slug = trim((string)($_GET['slug'] ?? ''));
if ($slug === '') {
    header('Location: /blog');
    exit;
}

try {
    $post = blogGetBySlug($conn, $slug);
} catch (\Throwable $e) {
    $post = null;
}
if (!$post) {
    header('Location: /blog');
    exit;
}

// Increment views
try { blogIncrementViews($conn, (int)$post['id']); } catch (\Throwable $e) {}

// Related posts (same category first, then generic)
try {
    $cat = trim((string)($post['categoria'] ?? ''));
    if ($cat !== '') {
        $related = blogListFiltered($conn, 'publicado', 6, 0, '', $cat);
        $related = array_values(array_filter($related, static fn(array $p): bool => (int)$p['id'] !== (int)$post['id']));
    } else {
        $related = blogList($conn, 'publicado', 6);
        $related = array_values(array_filter($related, static fn(array $p): bool => (int)$p['id'] !== (int)$post['id']));
    }
    $related = array_slice($related, 0, 3);
} catch (\Throwable $e) {
    $related = [];
}

// Reading time estimation
$wordCount   = str_word_count(strip_tags((string)$post['conteudo']));
$readingTime = max(1, (int)ceil($wordCount / 200));

$cartCount   = function_exists('sfCartCount') ? sfCartCount() : 0;
$currentPage = 'blog';
$pageTitle   = (string)$post['titulo'] . ' — Blog';
$shareUrl    = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? '') . '/blog/' . htmlspecialchars($slug);
include __DIR__ . '/../views/partials/header.php';
include __DIR__ . '/../views/partials/storefront_nav.php';
?>

<div class="min-h-screen bg-blackx">
    <article class="max-w-3xl mx-auto px-4 sm:px-6 pt-8 pb-12">

        <!-- Breadcrumb -->
        <nav class="flex items-center gap-2 text-sm text-zinc-500 mb-6 animate-fade-in">
            <a href="/" class="hover:text-greenx transition-colors">Início</a>
            <i data-lucide="chevron-right" class="w-3.5 h-3.5"></i>
            <a href="/blog" class="hover:text-greenx transition-colors">Blog</a>
            <?php if (!empty($post['categoria'])): ?>
            <i data-lucide="chevron-right" class="w-3.5 h-3.5"></i>
            <a href="/blog/categoria/<?= htmlspecialchars(blogGenerateSlug((string)$post['categoria']), ENT_QUOTES, 'UTF-8') ?>" class="hover:text-greenx transition-colors"><?= htmlspecialchars((string)$post['categoria']) ?></a>
            <?php endif; ?>
            <i data-lucide="chevron-right" class="w-3.5 h-3.5"></i>
            <span class="text-zinc-300 truncate max-w-[200px]"><?= htmlspecialchars((string)$post['titulo']) ?></span>
        </nav>

        <!-- Header -->
        <header class="mb-8 animate-fade-in-up">
            <?php if (!empty($post['categoria'])): ?>
            <a href="/blog/categoria/<?= htmlspecialchars(blogGenerateSlug((string)$post['categoria']), ENT_QUOTES, 'UTF-8') ?>"
               class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-greenx/10 border border-greenx/20 text-greenx text-xs font-bold uppercase tracking-wider hover:bg-greenx/15 transition-colors mb-4">
                <i data-lucide="tag" class="w-3 h-3"></i>
                <?= htmlspecialchars((string)$post['categoria']) ?>
            </a>
            <?php endif; ?>

            <h1 class="text-3xl sm:text-4xl font-black leading-tight tracking-tight mb-5">
                <?= htmlspecialchars((string)$post['titulo']) ?>
            </h1>

            <?php if (!empty($post['resumo'])): ?>
            <p class="text-zinc-400 text-lg leading-relaxed mb-5"><?= htmlspecialchars((string)$post['resumo']) ?></p>
            <?php endif; ?>

            <div class="flex items-center justify-between flex-wrap gap-3">
                <div class="flex items-center gap-3 text-sm text-zinc-400">
                    <a href="/blog/autor/<?= (int)$post['author_id'] ?>" class="flex items-center gap-2 hover:text-greenx transition-colors">
                        <div class="w-9 h-9 rounded-full bg-gradient-to-br from-greenx/20 to-greenxd/10 border border-greenx/20 flex items-center justify-center">
                            <span class="text-xs font-bold text-greenx"><?= strtoupper(mb_substr((string)$post['author_nome'], 0, 1)) ?></span>
                        </div>
                        <span class="font-medium"><?= htmlspecialchars((string)$post['author_nome']) ?></span>
                    </a>
                    <span class="text-zinc-600">&middot;</span>
                    <span><?= date('d \d\e M, Y', strtotime((string)$post['criado_em'])) ?></span>
                    <span class="text-zinc-600">&middot;</span>
                    <span class="flex items-center gap-1"><i data-lucide="clock" class="w-3.5 h-3.5"></i> <?= $readingTime ?> min de leitura</span>
                    <span class="text-zinc-600">&middot;</span>
                    <span class="flex items-center gap-1"><i data-lucide="eye" class="w-3.5 h-3.5"></i> <?= number_format((int)$post['visualizacoes']) ?></span>
                </div>
            </div>
        </header>

        <!-- Cover image -->
        <?php if (!empty($post['imagem'])): ?>
        <div class="rounded-2xl overflow-hidden border border-white/[0.06] mb-8 animate-fade-in-up stagger-2">
            <img src="<?= htmlspecialchars(blogImgSrc2((string)$post['imagem'])) ?>"
                 alt="<?= htmlspecialchars((string)$post['titulo']) ?>"
                 class="w-full object-cover max-h-[420px]">
        </div>
        <?php endif; ?>

        <!-- Content -->
        <div class="prose prose-invert prose-sm sm:prose-base max-w-none text-zinc-300 leading-relaxed animate-fade-in-up stagger-3
                    [&_a]:text-greenx [&_img]:rounded-xl [&_img]:max-w-full
                    [&_h2]:text-xl [&_h2]:font-bold [&_h2]:mt-10 [&_h2]:mb-4
                    [&_h3]:text-lg [&_h3]:font-semibold [&_h3]:mt-8 [&_h3]:mb-3
                    [&_blockquote]:border-l-greenx/50 [&_blockquote]:bg-white/[0.02] [&_blockquote]:rounded-r-xl [&_blockquote]:px-6 [&_blockquote]:py-4 [&_blockquote]:italic
                    [&_code]:bg-white/[0.06] [&_code]:px-1.5 [&_code]:py-0.5 [&_code]:rounded [&_code]:text-greenx/80
                    [&_pre]:bg-white/[0.04] [&_pre]:rounded-xl [&_pre]:border [&_pre]:border-white/[0.06]
                    [&_ul]:space-y-1 [&_ol]:space-y-1
                    [&_hr]:border-white/[0.06] [&_hr]:my-8">
            <?= $post['conteudo'] ?>
        </div>

        <!-- Share & Navigation -->
        <div class="mt-10 pt-6 border-t border-white/[0.06] animate-fade-in-up stagger-4">
            <div class="flex items-center justify-between flex-wrap gap-4">
                <a href="/blog" class="inline-flex items-center gap-2 text-sm text-zinc-400 hover:text-greenx transition-colors">
                    <i data-lucide="arrow-left" class="w-4 h-4"></i> Voltar ao blog
                </a>
                <div class="flex items-center gap-2 text-sm text-zinc-500">
                    <span class="text-xs uppercase tracking-wider font-semibold mr-1">Compartilhar</span>
                    <a href="https://twitter.com/intent/tweet?url=<?= urlencode($shareUrl) ?>&text=<?= urlencode((string)$post['titulo']) ?>"
                       target="_blank" rel="noopener"
                       class="w-9 h-9 rounded-xl bg-white/[0.04] border border-white/[0.08] flex items-center justify-center hover:border-greenx/30 hover:text-greenx transition-all"
                       title="Twitter">
                        <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-5.214-6.817L4.99 21.75H1.68l7.73-8.835L1.254 2.25H8.08l4.713 6.231zm-1.161 17.52h1.833L7.084 4.126H5.117z"/></svg>
                    </a>
                    <a href="https://wa.me/?text=<?= urlencode((string)$post['titulo'] . ' — ' . $shareUrl) ?>"
                       target="_blank" rel="noopener"
                       class="w-9 h-9 rounded-xl bg-white/[0.04] border border-white/[0.08] flex items-center justify-center hover:border-greenx/30 hover:text-greenx transition-all"
                       title="WhatsApp">
                        <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/></svg>
                    </a>
                    <button onclick="navigator.clipboard.writeText('<?= $shareUrl ?>');this.innerHTML='<i data-lucide=&quot;check&quot; class=&quot;w-4 h-4 text-greenx&quot;></i>';setTimeout(()=>{this.innerHTML='<i data-lucide=&quot;link&quot; class=&quot;w-4 h-4&quot;></i>';lucide.createIcons()},1500);lucide.createIcons()"
                            class="w-9 h-9 rounded-xl bg-white/[0.04] border border-white/[0.08] flex items-center justify-center hover:border-greenx/30 hover:text-greenx transition-all"
                            title="Copiar link">
                        <i data-lucide="link" class="w-4 h-4"></i>
                    </button>
                </div>
            </div>
        </div>
    </article>

    <!-- Related posts -->
    <?php if (!empty($related)): ?>
    <section class="max-w-[1600px] mx-auto px-4 sm:px-6 pb-12">
        <div class="border-t border-white/[0.06] pt-10">
            <h2 class="text-xl font-bold mb-6 flex items-center gap-2">
                <i data-lucide="newspaper" class="w-5 h-5 text-greenx"></i>
                Outros posts<?php if (!empty($post['categoria'])): ?> em <span class="text-greenx"><?= htmlspecialchars((string)$post['categoria']) ?></span><?php endif; ?>
            </h2>
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
                <?php foreach ($related as $rp): ?>
                <article class="product-card group bg-blackx2 border border-white/[0.06] rounded-2xl overflow-hidden hover:border-greenx/15 transition-all duration-300">
                    <a href="/blog/<?= htmlspecialchars((string)$rp['slug']) ?>" class="block relative">
                        <?php if (!empty($rp['imagem'])): ?>
                        <div class="aspect-[16/9] overflow-hidden bg-blackx">
                            <img src="<?= blogImgSrc2($rp['imagem']) ?>"
                                 alt="" class="w-full h-full object-cover group-hover:scale-[1.03] transition-transform duration-500" loading="lazy">
                        </div>
                        <?php else: ?>
                        <div class="aspect-[16/9] bg-gradient-to-br from-greenx/10 via-greenx/5 to-transparent flex items-center justify-center">
                            <i data-lucide="file-text" class="w-10 h-10 text-greenx/20"></i>
                        </div>
                        <?php endif; ?>
                        <?php if (!empty($rp['categoria'])): ?>
                        <span class="absolute top-3 left-3 px-2 py-0.5 rounded-full bg-black/60 backdrop-blur-sm border border-white/10 text-[10px] font-bold uppercase tracking-wider text-white/80">
                            <?= htmlspecialchars((string)$rp['categoria']) ?>
                        </span>
                        <?php endif; ?>
                    </a>
                    <div class="p-4 space-y-2">
                        <div class="flex items-center gap-2 text-xs text-zinc-500">
                            <span><?= htmlspecialchars((string)$rp['author_nome']) ?></span>
                            <span>&middot;</span>
                            <span><?= date('d/m/Y', strtotime((string)$rp['criado_em'])) ?></span>
                        </div>
                        <a href="/blog/<?= htmlspecialchars((string)$rp['slug']) ?>"
                           class="block font-bold leading-tight group-hover:text-greenx transition-colors line-clamp-2">
                            <?= htmlspecialchars((string)$rp['titulo']) ?>
                        </a>
                    </div>
                </article>
                <?php endforeach; ?>
            </div>
        </div>
    </section>
    <?php endif; ?>
</div>

<?php
include __DIR__ . '/../views/partials/storefront_footer.php';
include __DIR__ . '/../views/partials/footer.php';
?>
