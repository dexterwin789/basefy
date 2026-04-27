<?php
declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) session_start();

require_once __DIR__ . '/../src/db.php';
require_once __DIR__ . '/../src/storefront.php';
require_once __DIR__ . '/../src/affiliates.php';
require_once __DIR__ . '/../src/upload_paths.php';
require_once __DIR__ . '/../src/media.php';

$userId     = (int)($_SESSION['user_id'] ?? 0);
$userRole   = (string)($_SESSION['user']['role'] ?? 'usuario');
$isLoggedIn = $userId > 0;

$conn = (new Database())->connect();

// Affiliate referral tracking
affHandleReferral($conn);

$feedback = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && (string)($_POST['action'] ?? '') === 'add_cart') {
    $varPost = trim((string)($_POST['variante'] ?? ''));
    sfCartAdd((int)($_POST['product_id'] ?? 0), max(1, (int)($_POST['qty'] ?? 1)), $varPost !== '' ? $varPost : null);
    $feedback = 'Produto adicionado ao carrinho!';
}

$q          = trim((string)($_GET['q'] ?? ''));
$destaques  = sfListProducts($conn, ['limit' => 10, 'q' => $q]);
$populares  = sfListProducts($conn, ['limit' => 5]);
$categorias = sfListCategories($conn, 'produto');
$cartCount  = sfCartCount();

// Blog posts (safe)
$blogPosts = [];
try {
    require_once __DIR__ . '/../src/blog.php';
    if (blogIsEnabled($conn)) {
        $blogPosts = blogLatest($conn, 3);
    }
} catch (\Throwable $e) {}

// Fallback example blog posts if none exist
if (empty($blogPosts)) {
    $blogPosts = [
        ['slug' => '#', 'imagem' => '', 'titulo' => 'Como vender suas contas de jogos com segurança', 'resumo' => 'Dicas essenciais para anunciar suas contas no marketplace, garantir a segurança da transação e evitar problemas com compradores.', 'author_nome' => 'Admin', 'criado_em' => date('Y-m-d H:i:s', strtotime('-2 days'))],
        ['slug' => '#', 'imagem' => '', 'titulo' => 'Gift Cards: Guia completo de compra e venda', 'resumo' => 'Tudo sobre o mercado de gift cards digitais — Steam, PlayStation, Xbox, Google Play e mais. Saiba como lucrar com revendas.', 'author_nome' => 'Admin', 'criado_em' => date('Y-m-d H:i:s', strtotime('-5 days'))],
        ['slug' => '#', 'imagem' => '', 'titulo' => 'Novidades da plataforma: PIX instantâneo e escrow', 'resumo' => 'Conheça o sistema de pagamento PIX com confirmação automática e o escrow que protege compradores e vendedores.', 'author_nome' => 'Admin', 'criado_em' => date('Y-m-d H:i:s', strtotime('-7 days'))],
    ];
}



$currentPage = 'home';
$pageTitle   = 'Basefy — Marketplace Digital';

// Affiliate rules for CTA section
$affRulesHome = affRules($conn);

include __DIR__ . '/../views/partials/header.php';
include __DIR__ . '/../views/partials/storefront_nav.php';
?>

<style>
@media (min-width: 1024px) {
    .hero-section {
        min-height: 100vh;
        padding-top: 100px !important;
        padding-bottom: 0px !important;
        margin-bottom: -200px;
        margin-top: -150px;
    }
    .hero-grid { grid-template-columns: 760px 1fr; }
    .hero-title { font-size: 60px !important; font-weight: 600 !important; }
    .hero-logo-img.w-\[960px\] { width: 1300px !important; height: 1300px !important; }
    .lg\:-ml-\[95px\] { margin-left: -420px !important; }
}
@media (max-width: 1023px) {
    .hero-section { min-height: auto; padding-top: 108px !important; padding-bottom: 0 !important; margin-bottom: -36px; display: block; }
    .hero-grid { display: flex; flex-direction: column; gap: 0; }
    .hero-badge { font-size: 16px !important; }
    .hero-title { font-size: 44px !important; line-height: 112% !important; }
    .hero-copy { font-size: 22px !important; line-height: 118% !important; }
    .hero-logo-wrap { min-height: 0 !important; height: clamp(280px, 52vw, 430px); margin-top: -28px; overflow: hidden; justify-content: center !important; }
    .hero-logo-img { width: clamp(440px, 118vw, 620px) !important; height: clamp(440px, 118vw, 620px) !important; transform: translateX(12%); }
}
@media (max-width: 480px) {
    .hero-section { padding-top: 140px !important; margin-bottom: -50px; }
    .hero-badge { font-size: 14px !important; padding: 8px 14px !important; }
    .hero-title { font-size: 28px !important; line-height: 108% !important; }
    .hero-copy { font-size: 17px !important; line-height: 116% !important; }
    .hero-logo-wrap { height: 500px; margin-top: -385px; margin-left: 158px; }
    .hero-logo-img { width: 430px !important; height: 430px !important; transform: translateX(11%); }
}
</style>

<div class="min-h-screen bg-blackx">

    <!-- =========== HERO — BASEFY PREMIUM =========== -->
    <section class="hero-section relative overflow-hidden min-h-screen pt-24 sm:pt-24 pb-10 flex items-center">
        <div class="absolute inset-0 bg-[#07000f]"></div>
        <div class="absolute inset-0 opacity-[0.08]" style="background-image:linear-gradient(rgba(168,85,247,.25) 1px,transparent 1px),linear-gradient(90deg,rgba(168,85,247,.25) 1px,transparent 1px);background-size:64px 64px"></div>
        <div class="absolute top-[28%] right-[16%] w-[420px] h-[420px] bg-purple-600/20 blur-[120px] pointer-events-none"></div>
        <div class="absolute bottom-0 left-0 right-0 h-28 bg-gradient-to-t from-blackx to-transparent"></div>

        <div class="relative w-full max-w-[1440px] mx-auto px-4 sm:px-6">
            <div class="hero-grid grid lg:grid-cols-[760px_1fr] gap-0 items-center">
                <div class="max-w-[760px] hero-reveal relative z-10">
                    <div class="hero-badge inline-flex items-center rounded-full bg-purple-500/10 px-5 py-2.5 text-zinc-300 shadow-lg shadow-purple-500/10" style="font-family:Gotham,Montserrat,sans-serif;font-weight:300;font-size:22.99px;line-height:108%;letter-spacing:0;border:0.85px solid #BE5DFF;">
                        Marketplace nº 1 de ativos digitais
                    </div>

                    <h1 class="hero-title mt-6 text-white" style="font-family:Gotham,Montserrat,sans-serif;font-weight:600;font-size:60px;line-height:110%;letter-spacing:0;max-width:760px;">
                        <span class="block">Compre e venda <span style="color:#A521FE">ativos</span></span>
                        <span class="block"><span style="color:#A521FE">digitais</span> com pagamento</span>
                        <span class="block">protegido</span>
                    </h1>

                    <p class="hero-copy mt-6 text-zinc-400 max-w-[760px]" style="font-family:Gotham,Montserrat,sans-serif;font-weight:325;font-size:32px;line-height:108%;letter-spacing:0;">
                        <span class="block">Contas, gift cards e muito mais. Pix instantâneo</span>
                        <span class="block">mais liberação só após confirmação.</span>
                    </p>

                    <a href="<?= BASE_PATH ?>/categorias" class="mt-8 inline-flex items-center gap-2 rounded-full bg-gradient-to-r from-purple-500 to-fuchsia-500 px-6 py-3.5 text-base font-bold text-white shadow-xl shadow-purple-600/30 hover:shadow-purple-600/45 hover:scale-[1.02] active:scale-[0.98] transition-all">
                        Buscar produtos <i data-lucide="arrow-right" class="w-5 h-5"></i>
                    </a>
                </div>

                <div class="hero-logo-wrap relative min-h-[420px] lg:min-h-[680px] flex items-center justify-center lg:justify-start pointer-events-none">
                    <img src="<?= BASE_PATH ?>/assets/img/logobanner.png" alt="" class="hero-logo-img w-[960px] h-[960px] max-w-none object-contain hero-reveal lg:-ml-[95px] lg:-mr-[120px] lg:-mt-[28px]" style="animation-delay:.15s;filter:drop-shadow(-12.06px 9.87px 33.98px rgba(165,33,254,.20)) drop-shadow(-46.04px 41.66px 62.49px rgba(165,33,254,.17)) drop-shadow(-104.15px 93.18px 84.41px rgba(165,33,254,.10)) drop-shadow(-186.37px 165.54px 99.76px rgba(165,33,254,.03));">
                </div>
            </div>
        </div>
    </section>

    <!-- =========== TRUST MARQUEE =========== -->
    <section class="relative overflow-hidden border-y border-white/[0.04] bg-white/[0.01] py-4">
        <div class="trust-marquee flex items-center gap-10 sm:gap-16 whitespace-nowrap">
            <?php for ($ti = 0; $ti < 4; $ti++): ?>
            <div class="flex items-center gap-10 sm:gap-16 shrink-0 trust-marquee-track">
                <div class="flex items-center gap-2.5 marquee-item text-xs sm:text-sm"><span class="w-2.5 h-2.5 rounded-full bg-purple-500 shadow-[0_0_14px_rgba(168,85,247,.9)]"></span><span>Dados criptografados</span></div>
                <div class="flex items-center gap-2.5 marquee-item text-xs sm:text-sm"><span class="w-2.5 h-2.5 rounded-full bg-purple-500 shadow-[0_0_14px_rgba(168,85,247,.9)]"></span><span>Reembolso garantido</span></div>
                <div class="flex items-center gap-2.5 marquee-item text-xs sm:text-sm"><span class="w-2.5 h-2.5 rounded-full bg-purple-500 shadow-[0_0_14px_rgba(168,85,247,.9)]"></span><span>Vendedores verificados</span></div>
                <div class="flex items-center gap-2.5 marquee-item text-xs sm:text-sm"><span class="w-2.5 h-2.5 rounded-full bg-purple-500 shadow-[0_0_14px_rgba(168,85,247,.9)]"></span><span>Entrega imediata</span></div>
                <div class="flex items-center gap-2.5 marquee-item text-xs sm:text-sm"><span class="w-2.5 h-2.5 rounded-full bg-purple-500 shadow-[0_0_14px_rgba(168,85,247,.9)]"></span><span>Escrow seguro</span></div>
                <div class="flex items-center gap-2.5 marquee-item text-xs sm:text-sm"><span class="w-2.5 h-2.5 rounded-full bg-purple-500 shadow-[0_0_14px_rgba(168,85,247,.9)]"></span><span>PIX instantâneo</span></div>
                <div class="flex items-center gap-2.5 marquee-item text-xs sm:text-sm"><span class="w-2.5 h-2.5 rounded-full bg-purple-500 shadow-[0_0_14px_rgba(168,85,247,.9)]"></span><span>Suporte 24h</span></div>
            </div>
            <?php endfor; ?>
        </div>
    </section>

    <?php if ($feedback !== ''): ?>
    <div class="max-w-7xl mx-auto px-4 sm:px-6 mt-8 mb-6 relative z-10 animate-scale-in">
        <div class="flex items-center gap-3 rounded-2xl border border-greenx/30 bg-greenx/[0.06] backdrop-blur-sm px-5 py-3.5">
            <div class="w-8 h-8 rounded-full bg-greenx/20 flex items-center justify-center flex-shrink-0">
                <i data-lucide="check" class="w-4 h-4 text-greenx"></i>
            </div>
            <p class="text-sm text-greenx"><?= htmlspecialchars($feedback, ENT_QUOTES, 'UTF-8') ?></p>
        </div>
    </div>
    <?php endif; ?>

    <!-- =========== CATEGORIAS — Premium cards =========== -->
    <?php if ($categorias): ?>
    <section class="max-w-7xl mx-auto px-4 sm:px-6 py-14 sm:py-20">
        <div class="flex items-center justify-between mb-8 sm:mb-10">
            <div>
                <div class="inline-flex items-center gap-2 px-3 py-1.5 rounded-full bg-greenx/10 border border-greenx/20 text-greenx text-[11px] font-bold uppercase tracking-wider mb-3">
                    <i data-lucide="layout-grid" class="w-3 h-3"></i>
                    Categorias
                </div>
                <h2 class="text-xl sm:text-2xl font-bold">Explorar categorias</h2>
                <p class="text-sm text-zinc-500 mt-1">Navegue por tipo de produto</p>
            </div>
            <a href="<?= BASE_PATH ?>/categorias" class="hidden sm:inline-flex items-center gap-1.5 text-xs text-greenx hover:underline font-semibold">Ver todas <i data-lucide="arrow-right" class="w-3.5 h-3.5"></i></a>
        </div>
        <?php
            $catIcons = ['gamepad-2','swords','crown','gem','box','tag','music','film','book-open','cpu','palette','globe'];
        ?>
        <div class="flex gap-2.5 overflow-x-auto pb-3 scrollbar-hide snap-x snap-mandatory -mx-4 px-4 sm:mx-0 sm:px-0 sm:grid sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 sm:gap-5 sm:overflow-visible">
            <?php foreach ($categorias as $i => $cat):
                $icon = $catIcons[$i % count($catIcons)];
                $catImg = trim((string)($cat['imagem'] ?? ''));
                $catImgUrl = ($catImg !== '' && $catImg !== 'https://placehold.co/1200x800/111827/9ca3af?text=Produto') ? sfImageUrl($catImg) : '';
            ?>
            <a href="<?= sfCategoryUrl($cat) ?>"
               class="cat-card group relative flex flex-col items-center justify-end text-center rounded-2xl border border-white/[0.06] bg-blackx2 overflow-hidden hover:border-greenx/30 transition-all duration-500 animate-fade-in stagger-<?= min($i + 1, 6) ?> min-w-[140px] sm:min-w-0 snap-start <?= $catImgUrl ? 'aspect-[4/3]' : 'py-6 px-3 sm:py-8 sm:px-4' ?>">
                <?php if ($catImgUrl): ?>
                <img src="<?= htmlspecialchars($catImgUrl, ENT_QUOTES, 'UTF-8') ?>" alt="" class="absolute inset-0 w-full h-full object-cover opacity-60 group-hover:opacity-80 group-hover:scale-110 transition-all duration-700" loading="lazy">
                <div class="absolute inset-0 bg-gradient-to-t from-black/95 via-black/60 to-black/20"></div>
                <div class="absolute inset-0 opacity-0 group-hover:opacity-100 transition-opacity duration-500 bg-gradient-to-t from-greenx/20 via-transparent to-transparent"></div>
                <div class="relative z-10 w-full pb-5 px-4">
                    <span class="inline-block px-3 py-1.5 rounded-lg bg-black/60 backdrop-blur-sm border border-white/10 text-sm sm:text-base font-bold text-white keep-white group-hover:bg-greenx/90 group-hover:border-greenx/50 transition-all duration-300 cat-name-pill"><?= htmlspecialchars((string)$cat['nome'], ENT_QUOTES, 'UTF-8') ?></span>
                </div>
                <?php else: ?>
                <div class="w-14 h-14 rounded-2xl bg-greenx/10 border border-greenx/20 flex items-center justify-center mb-4 group-hover:bg-greenx/20 group-hover:scale-110 group-hover:rotate-3 transition-all duration-300">
                    <i data-lucide="<?= $icon ?>" class="w-6 h-6 text-greenx"></i>
                </div>
                <p class="text-sm font-bold group-hover:text-greenx transition-colors line-clamp-2 leading-tight"><?= htmlspecialchars((string)$cat['nome'], ENT_QUOTES, 'UTF-8') ?></p>
                <?php endif; ?>
            </a>
            <?php endforeach; ?>
        </div>
        <div class="mt-5 text-center sm:hidden">
            <a href="<?= BASE_PATH ?>/categorias" class="text-xs text-greenx hover:underline font-semibold inline-flex items-center gap-1">Ver todas as categorias <i data-lucide="arrow-right" class="w-3.5 h-3.5"></i></a>
        </div>
    </section>
    <?php endif; ?>

    <!-- =========== PRODUTOS EM DESTAQUE =========== -->
    <section class="max-w-7xl mx-auto px-4 sm:px-6 py-10 sm:py-14">
        <div class="flex items-center justify-between mb-8 sm:mb-10">
            <div>
                <div class="inline-flex items-center gap-2 px-3 py-1.5 rounded-full bg-greenx/10 border border-greenx/20 text-greenx text-[11px] font-bold uppercase tracking-wider mb-3">
                    <i data-lucide="flame" class="w-3 h-3"></i>
                    <?= $q !== '' ? 'Busca' : 'Destaques' ?>
                </div>
                <h2 class="text-2xl sm:text-3xl font-bold">
                    <?= $q !== '' ? 'Resultados para "' . htmlspecialchars($q, ENT_QUOTES, 'UTF-8') . '"' : 'Em destaque' ?>
                </h2>
                <p class="text-sm text-zinc-500 mt-1"><?= $q !== '' ? count($destaques) . ' produto(s) encontrado(s)' : 'Selecionados especialmente para você' ?></p>
            </div>
            <a href="<?= BASE_PATH ?>/categorias"
               class="hidden sm:inline-flex items-center gap-1.5 text-xs text-greenx hover:underline font-semibold">
                Ver tudo <i data-lucide="arrow-right" class="w-3.5 h-3.5"></i>
            </a>
        </div>

        <?php if ($destaques): ?>
        <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 gap-2.5 sm:gap-5">
            <?php foreach ($destaques as $i => $p): ?>
            <article class="product-card group bg-blackx2 border border-white/[0.06] rounded-2xl overflow-hidden flex flex-col hover:border-greenx/20 hover:shadow-2xl hover:shadow-greenx/[0.06] hover:-translate-y-1 transition-all duration-400 animate-fade-in-up stagger-<?= min($i + 1, 8) ?>">
                <a href="<?= sfProductUrl($p) ?>" class="block relative overflow-hidden">
                    <div class="aspect-square overflow-hidden bg-blackx">
                        <img src="<?= htmlspecialchars(sfImageUrl((string)($p['imagem'] ?? '')), ENT_QUOTES, 'UTF-8') ?>"
                             alt="<?= htmlspecialchars((string)($p['nome'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                             class="w-full h-full object-cover group-hover:scale-110 transition-transform duration-700" loading="lazy">
                    </div>
                    <div class="absolute inset-0 bg-gradient-to-t from-black/30 to-transparent opacity-0 group-hover:opacity-100 transition-opacity duration-300"></div>
                    <span class="absolute top-2 left-2 sm:top-2.5 sm:left-2.5 px-2 py-0.5 sm:px-2.5 sm:py-1 rounded-lg text-[8px] sm:text-[9px] font-bold uppercase tracking-wide bg-greenx text-white shadow-md">
                        <?= htmlspecialchars((string)($p['categoria_nome'] ?? 'Geral'), ENT_QUOTES, 'UTF-8') ?>
                    </span>
                </a>
                <button type="button" class="fav-btn absolute top-2 right-2 sm:top-2.5 sm:right-2.5 w-7 h-7 sm:w-8 sm:h-8 rounded-full bg-black/50 backdrop-blur-sm border border-white/10 flex items-center justify-center text-zinc-400 hover:text-red-400 hover:border-red-400/40 hover:bg-red-500/10 transition-all z-10" data-product-id="<?= (int)$p['id'] ?>" title="Favoritar">
                    <i data-lucide="heart" class="w-3 h-3 sm:w-3.5 sm:h-3.5"></i>
                </button>
                <div class="p-1.5 sm:p-4 flex flex-col flex-1">
                    <a href="<?= sfProductUrl($p) ?>"
                       class="font-bold text-[10px] sm:text-sm line-clamp-2 min-h-[2rem] sm:min-h-[2.4rem] leading-snug hover:text-greenx transition-colors block">
                        <?= htmlspecialchars((string)($p['nome'] ?? 'Produto'), ENT_QUOTES, 'UTF-8') ?>
                    </a>
                    <?php if (!empty($p['vendedor_nome'])): ?>
                    <div class="hidden sm:flex items-center gap-1.5 text-[10px] text-zinc-500 mt-2">
                        <i data-lucide="store" class="w-3 h-3 text-greenx/70 shrink-0"></i>
                        <span class="truncate"><?= htmlspecialchars((string)$p['vendedor_nome'], ENT_QUOTES, 'UTF-8') ?></span>
                    </div>
                    <?php endif; ?>
                    <div class="mt-auto pt-2">
                        <span class="text-xs sm:text-base font-bold text-greenx whitespace-nowrap"><?= sfDisplayPrice($p) ?></span>
                    </div>
                    <?php
                    $hasVariants = (($p['tipo'] ?? '') === 'dinamico' && !empty($p['variantes']));
                    $varsJson = $hasVariants ? htmlspecialchars(is_string($p['variantes']) ? $p['variantes'] : json_encode($p['variantes']), ENT_QUOTES, 'UTF-8') : '';
                    ?>
                    <form method="post" class="mt-2 sm:mt-3"
                        <?= $hasVariants ? 'data-variants="' . $varsJson . '" data-product-name="' . htmlspecialchars((string)($p['nome'] ?? ''), ENT_QUOTES, 'UTF-8') . '" data-product-image="' . htmlspecialchars(sfImageUrl((string)($p['imagem'] ?? '')), ENT_QUOTES, 'UTF-8') . '"' : '' ?>>
                        <input type="hidden" name="action" value="add_cart">
                        <input type="hidden" name="product_id" value="<?= (int)$p['id'] ?>">
                        <input type="hidden" name="qty" value="1">
                        <button class="w-full flex items-center justify-center gap-1 sm:gap-1.5 rounded-xl bg-gradient-to-r from-greenx to-greenxd hover:from-greenx2 hover:to-greenxd text-white font-bold px-2 py-2 sm:px-3 sm:py-2.5 text-[10px] sm:text-xs shadow-lg shadow-greenx/15 hover:shadow-greenx/30 hover:scale-[1.02] active:scale-[0.98] transition-all">
                            <i data-lucide="shopping-bag" class="w-3 h-3 sm:w-3.5 sm:h-3.5"></i>
                            Comprar
                        </button>
                    </form>
                </div>
            </article>
            <?php endforeach; ?>
        </div>
        <?php else: ?>
        <div class="rounded-3xl border border-white/[0.06] bg-blackx2 p-12 sm:p-16 text-center">
            <div class="w-16 h-16 rounded-2xl bg-white/[0.04] border border-white/[0.06] flex items-center justify-center mx-auto mb-4">
                <i data-lucide="package-open" class="w-7 h-7 text-zinc-600"></i>
            </div>
            <h3 class="text-lg font-semibold text-zinc-300">Nenhum produto encontrado</h3>
            <p class="text-sm text-zinc-500 mt-2 max-w-sm mx-auto">
                <?= $q !== '' ? 'Não encontramos resultados para sua busca. Tente termos diferentes.' : 'Produtos serão exibidos aqui quando disponíveis.' ?>
            </p>
            <?php if ($q !== ''): ?>
            <a href="<?= BASE_PATH ?>/" class="inline-flex mt-5 rounded-xl bg-white/[0.06] border border-white/[0.08] px-5 py-2.5 text-sm text-zinc-300 hover:text-white hover:border-white/[0.15] transition-all">Limpar busca</a>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </section>

    <!-- =========== COMO FUNCIONA — Timeline flow =========== -->
    <?php if ($q === ''): ?>
    <section class="relative overflow-hidden">
        <div class="absolute inset-0 bg-[radial-gradient(ellipse_80%_50%_at_50%_50%,rgba(var(--t-accent-rgb),0.05),transparent)]"></div>
        <div class="relative max-w-7xl mx-auto px-4 sm:px-6 py-16 sm:py-24">
            <div class="text-center mb-12 sm:mb-16">
                <div class="inline-flex items-center gap-2 px-3 py-1.5 rounded-full bg-greenx/10 border border-greenx/20 text-greenx text-[11px] font-bold uppercase tracking-wider mb-4">
                    <i data-lucide="info" class="w-3 h-3"></i>
                    Simples e rápido
                </div>
                <h2 class="text-2xl sm:text-4xl font-black">Como funciona?</h2>
                <p class="text-sm sm:text-base text-zinc-500 mt-3 max-w-lg mx-auto">Três passos simples para comprar com total segurança</p>
            </div>

            <div class="relative max-w-5xl mx-auto">
                <!-- Connecting line (desktop) -->
                <div class="hidden sm:block absolute top-[60px] left-[16.5%] right-[16.5%] h-px bg-gradient-to-r from-transparent via-greenx/20 to-transparent"></div>

                <div class="grid grid-cols-1 sm:grid-cols-3 gap-6 sm:gap-8">
                    <div class="relative group text-center">
                        <div class="como-icon relative z-10 w-[72px] h-[72px] rounded-2xl bg-greenx/10 border-2 border-greenx/30 flex items-center justify-center mx-auto mb-6 group-hover:bg-greenx group-hover:border-greenx group-hover:scale-110 transition-all duration-300 shadow-lg shadow-greenx/10">
                            <i data-lucide="search" class="w-7 h-7 text-greenx como-icon-svg transition-colors"></i>
                            <div class="absolute -top-2 -right-2 w-7 h-7 rounded-full bg-greenx text-white flex items-center justify-center text-[11px] font-black shadow-lg shadow-greenx/40">1</div>
                        </div>
                        <h3 class="text-lg font-bold mb-2">Escolha o produto</h3>
                        <p class="text-sm text-zinc-500 leading-relaxed max-w-[240px] mx-auto">Navegue pelo catálogo e encontre exatamente o que precisa</p>
                    </div>
                    <div class="relative group text-center">
                        <div class="como-icon relative z-10 w-[72px] h-[72px] rounded-2xl bg-greenx/10 border-2 border-greenx/30 flex items-center justify-center mx-auto mb-6 group-hover:bg-greenx group-hover:border-greenx group-hover:scale-110 transition-all duration-300 shadow-lg shadow-greenx/10">
                            <i data-lucide="qr-code" class="w-7 h-7 text-greenx como-icon-svg transition-colors"></i>
                            <div class="absolute -top-2 -right-2 w-7 h-7 rounded-full bg-greenx text-white flex items-center justify-center text-[11px] font-black shadow-lg shadow-greenx/40">2</div>
                        </div>
                        <h3 class="text-lg font-bold mb-2">Pague via PIX</h3>
                        <p class="text-sm text-zinc-500 leading-relaxed max-w-[240px] mx-auto">Confirmação automática e valor protegido por Escrow</p>
                    </div>
                    <div class="relative group text-center">
                        <div class="como-icon relative z-10 w-[72px] h-[72px] rounded-2xl bg-greenx/10 border-2 border-greenx/30 flex items-center justify-center mx-auto mb-6 group-hover:bg-greenx group-hover:border-greenx group-hover:scale-110 transition-all duration-300 shadow-lg shadow-greenx/10">
                            <i data-lucide="package-check" class="w-7 h-7 text-greenx como-icon-svg transition-colors"></i>
                            <div class="absolute -top-2 -right-2 w-7 h-7 rounded-full bg-greenx text-white flex items-center justify-center text-[11px] font-black shadow-lg shadow-greenx/40">3</div>
                        </div>
                        <h3 class="text-lg font-bold mb-2">Receba seu produto</h3>
                        <p class="text-sm text-zinc-500 leading-relaxed max-w-[240px] mx-auto">Entrega digital automática com garantia total da plataforma</p>
                    </div>
                </div>
            </div>

            <div class="text-center mt-12">
                <a href="<?= BASE_PATH ?>/como_funciona"
                   class="inline-flex items-center gap-2 px-7 py-3.5 rounded-xl bg-greenx/10 border border-greenx/20 text-greenx font-bold text-sm hover:bg-greenx/20 hover:scale-[1.02] transition-all">
                    <i data-lucide="play-circle" class="w-4.5 h-4.5"></i>
                    Saiba mais
                </a>
            </div>
        </div>
    </section>
    <?php endif; ?>

    <!-- =========== MAIS POPULARES =========== -->
    <?php if ($populares && $q === ''): ?>
    <section class="max-w-7xl mx-auto px-4 sm:px-6 py-10 sm:py-14">
        <div class="flex items-center justify-between mb-8 sm:mb-10">
            <div>
                <div class="inline-flex items-center gap-2 px-3 py-1.5 rounded-full bg-greenx/10 border border-greenx/20 text-greenx text-[11px] font-bold uppercase tracking-wider mb-3">
                    <i data-lucide="trending-up" class="w-3 h-3"></i>
                    Popular
                </div>
                <h2 class="text-2xl sm:text-3xl font-bold">Mais populares</h2>
                <p class="text-sm text-zinc-500 mt-1">Produtos mais vendidos da plataforma</p>
            </div>
            <a href="<?= BASE_PATH ?>/categorias" class="hidden sm:inline-flex items-center gap-1.5 text-xs text-greenx hover:underline font-semibold">Ver todos <i data-lucide="arrow-right" class="w-3.5 h-3.5"></i></a>
        </div>
        <div class="flex gap-3 overflow-x-auto pb-3 scrollbar-hide snap-x snap-mandatory sm:mx-0 sm:px-0 sm:grid sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 sm:gap-5 sm:overflow-visible">
            <?php foreach ($populares as $i => $p): ?>
            <article class="product-card group bg-blackx2 border border-white/[0.06] rounded-2xl overflow-hidden flex flex-col hover:border-greenx/20 hover:shadow-2xl hover:shadow-greenx/[0.06] hover:-translate-y-1 transition-all duration-400 animate-fade-in-up stagger-<?= min($i + 1, 6) ?> min-w-[150px] sm:min-w-0 snap-start <?= $i === 0 ? 'ml-4 sm:ml-0' : '' ?><?= $i === count($populares) - 1 ? ' mr-4 sm:mr-0' : '' ?>">
                <a href="<?= sfProductUrl($p) ?>" class="block relative overflow-hidden">
                    <div class="aspect-square overflow-hidden bg-blackx">
                        <img src="<?= htmlspecialchars(sfImageUrl((string)($p['imagem'] ?? '')), ENT_QUOTES, 'UTF-8') ?>"
                             alt="<?= htmlspecialchars((string)($p['nome'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                             class="w-full h-full object-cover group-hover:scale-110 transition-transform duration-700" loading="lazy">
                    </div>
                    <div class="absolute inset-0 bg-gradient-to-t from-black/30 to-transparent opacity-0 group-hover:opacity-100 transition-opacity duration-300"></div>
                    <span class="absolute top-2 left-2 sm:top-2.5 sm:left-2.5 px-2 py-0.5 sm:px-2.5 sm:py-1 rounded-lg text-[8px] sm:text-[9px] font-bold uppercase tracking-wide bg-greenx text-white shadow-md">
                        <?= htmlspecialchars((string)($p['categoria_nome'] ?? 'Geral'), ENT_QUOTES, 'UTF-8') ?>
                    </span>
                </a>
                <button type="button" class="fav-btn absolute top-2 right-2 sm:top-2.5 sm:right-2.5 w-7 h-7 sm:w-8 sm:h-8 rounded-full bg-black/50 backdrop-blur-sm border border-white/10 flex items-center justify-center text-zinc-400 hover:text-red-400 hover:border-red-400/40 hover:bg-red-500/10 transition-all z-10" data-product-id="<?= (int)$p['id'] ?>" title="Favoritar">
                    <i data-lucide="heart" class="w-3 h-3 sm:w-3.5 sm:h-3.5"></i>
                </button>
                <div class="p-1.5 sm:p-4 flex flex-col flex-1">
                    <a href="<?= sfProductUrl($p) ?>" class="font-bold text-[10px] sm:text-sm line-clamp-2 min-h-[2rem] sm:min-h-[2.4rem] leading-snug hover:text-greenx transition-colors block">
                        <?= htmlspecialchars((string)($p['nome'] ?? 'Produto'), ENT_QUOTES, 'UTF-8') ?>
                    </a>
                    <?php if (!empty($p['vendedor_nome'])): ?>
                    <div class="hidden sm:flex items-center gap-1.5 text-[10px] text-zinc-500 mt-2">
                        <i data-lucide="store" class="w-3 h-3 text-greenx/70 shrink-0"></i>
                        <span class="truncate"><?= htmlspecialchars((string)$p['vendedor_nome'], ENT_QUOTES, 'UTF-8') ?></span>
                    </div>
                    <?php endif; ?>
                    <div class="mt-auto pt-2">
                        <span class="text-xs sm:text-base font-bold text-greenx whitespace-nowrap"><?= sfDisplayPrice($p) ?></span>
                    </div>
                    <?php
                    $hasVariants2 = (($p['tipo'] ?? '') === 'dinamico' && !empty($p['variantes']));
                    $varsJson2 = $hasVariants2 ? htmlspecialchars(is_string($p['variantes']) ? $p['variantes'] : json_encode($p['variantes']), ENT_QUOTES, 'UTF-8') : '';
                    ?>
                    <form method="post" class="mt-2 sm:mt-3"
                        <?= $hasVariants2 ? 'data-variants="' . $varsJson2 . '" data-product-name="' . htmlspecialchars((string)($p['nome'] ?? ''), ENT_QUOTES, 'UTF-8') . '" data-product-image="' . htmlspecialchars(sfImageUrl((string)($p['imagem'] ?? '')), ENT_QUOTES, 'UTF-8') . '"' : '' ?>>
                        <input type="hidden" name="action" value="add_cart">
                        <input type="hidden" name="product_id" value="<?= (int)$p['id'] ?>">
                        <input type="hidden" name="qty" value="1">
                        <button class="w-full flex items-center justify-center gap-1 sm:gap-1.5 rounded-xl bg-gradient-to-r from-greenx to-greenxd hover:from-greenx2 hover:to-greenxd text-white font-bold px-2 py-2 sm:px-3 sm:py-2.5 text-[10px] sm:text-xs shadow-lg shadow-greenx/15 hover:shadow-greenx/30 hover:scale-[1.02] active:scale-[0.98] transition-all">
                            <i data-lucide="shopping-bag" class="w-3 h-3 sm:w-3.5 sm:h-3.5"></i>
                            Comprar
                        </button>
                    </form>
                </div>
            </article>
            <?php endforeach; ?>
        </div>
    </section>
    <?php endif; ?>

    <!-- =========== BLOG POSTS =========== -->
    <?php if ($blogPosts && $q === ''): ?>
    <section class="max-w-7xl mx-auto px-4 sm:px-6 py-10 sm:py-14">
        <div class="flex items-center justify-between mb-8 sm:mb-10">
            <div>
                <div class="inline-flex items-center gap-2 px-3 py-1.5 rounded-full bg-greenx/10 border border-greenx/20 text-greenx text-[11px] font-bold uppercase tracking-wider mb-3">
                    <i data-lucide="newspaper" class="w-3 h-3"></i>
                    Blog
                </div>
                <h2 class="text-2xl sm:text-3xl font-bold">Blog &amp; Novidades</h2>
                <p class="text-sm text-zinc-500 mt-1">Dicas, novidades e conteúdo sobre o marketplace</p>
            </div>
            <a href="<?= BASE_PATH ?>/blog" class="hidden sm:inline-flex items-center gap-1.5 text-xs text-greenx hover:underline font-semibold">Ver todos <i data-lucide="arrow-right" class="w-3.5 h-3.5"></i></a>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 sm:gap-5">
            <?php foreach ($blogPosts as $bi => $bp): ?>
            <a href="<?= BASE_PATH ?>/blog/<?= htmlspecialchars((string)($bp['slug'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
               class="group rounded-2xl border border-white/[0.06] bg-blackx2 overflow-hidden hover:border-greenx/20 hover:shadow-lg hover:shadow-greenx/[0.03] hover:-translate-y-1 transition-all duration-400 animate-fade-in-up stagger-<?= min($bi + 1, 3) ?>">
                <?php if (!empty($bp['imagem'])): ?>
                <div class="aspect-[2/1] overflow-hidden bg-blackx">
                    <img src="<?= htmlspecialchars(sfImageUrl((string)$bp['imagem']), ENT_QUOTES, 'UTF-8') ?>"
                         alt="" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500" loading="lazy">
                </div>
                <?php else: ?>
                <div class="aspect-[2/1] bg-gradient-to-br from-greenx/10 to-transparent flex items-center justify-center">
                    <i data-lucide="book-open" class="w-10 h-10 text-greenx/30"></i>
                </div>
                <?php endif; ?>
                <div class="p-5 sm:p-6">
                    <h3 class="font-bold text-sm sm:text-base line-clamp-2 group-hover:text-greenx transition-colors mb-2"><?= htmlspecialchars((string)($bp['titulo'] ?? ''), ENT_QUOTES, 'UTF-8') ?></h3>
                    <?php if (!empty($bp['resumo'])): ?>
                    <p class="text-xs text-zinc-500 line-clamp-2 leading-relaxed"><?= htmlspecialchars((string)$bp['resumo'], ENT_QUOTES, 'UTF-8') ?></p>
                    <?php endif; ?>
                    <div class="flex items-center gap-2 mt-3 text-[10px] text-zinc-500">
                        <span><?= htmlspecialchars((string)($bp['author_nome'] ?? 'Admin'), ENT_QUOTES, 'UTF-8') ?></span>
                        <span class="w-1 h-1 rounded-full bg-zinc-600"></span>
                        <span><?= date('d/m/Y', strtotime((string)($bp['criado_em'] ?? 'now'))) ?></span>
                    </div>
                </div>
            </a>
            <?php endforeach; ?>
        </div>
    </section>
    <?php endif; ?>

    <!-- =========== AFFILIATE PROGRAM CTA =========== -->
    <?php if ($affRulesHome['program_enabled'] && $q === ''): ?>
    <section class="max-w-7xl mx-auto px-4 sm:px-6 py-8 sm:py-12">
        <div class="relative overflow-hidden rounded-3xl border border-greenx/20">
            <div class="absolute inset-0 bg-gradient-to-br from-greenx/[0.10] via-blackx2 to-greenxd/[0.10]"></div>
            <div class="absolute -top-20 -right-20 w-80 h-80 bg-greenx/[0.12] rounded-full blur-[120px] pointer-events-none"></div>
            <div class="absolute -bottom-20 -left-20 w-64 h-64 bg-greenxd/[0.10] rounded-full blur-[100px] pointer-events-none"></div>

            <div class="relative p-8 sm:p-10 md:p-12 lg:p-16">
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-10 lg:gap-14 items-center">
                    <div>
                        <div class="inline-flex items-center gap-2 px-3 py-1.5 rounded-full bg-greenx/10 border border-greenx/20 text-greenx text-xs font-bold mb-6">
                            <i data-lucide="share-2" class="w-3.5 h-3.5"></i>
                            Programa de Afiliados
                        </div>
                        <h2 class="text-3xl sm:text-4xl font-black leading-tight mb-5">
                            Indique e ganhe <span class="text-greenx">dinheiro</span> com cada venda
                        </h2>
                        <p class="text-zinc-400 text-sm sm:text-base leading-relaxed mb-8 max-w-md">
                            Compartilhe seu link exclusivo e receba comissão por cada compra realizada através da sua indicação.
                        </p>
                        <div class="flex flex-col sm:flex-row gap-2">
                            <?php if ($isLoggedIn): ?>
                            <a href="<?= BASE_PATH ?>/afiliados"
                               class="flex items-center justify-center px-5 py-2.5 rounded-xl bg-gradient-to-r from-greenx to-greenxd hover:from-greenx2 hover:to-greenxd text-white font-bold text-sm shadow-lg shadow-greenx/25 hover:shadow-greenx/40 hover:scale-[1.02] transition-all">
                                <i data-lucide="rocket" class="w-3.5 h-3.5 mr-1.5 shrink-0"></i> Ser Afiliado
                            </a>
                            <?php else: ?>
                            <a href="<?= BASE_PATH ?>/register"
                               class="flex items-center justify-center px-5 py-2.5 rounded-xl bg-gradient-to-r from-greenx to-greenxd hover:from-greenx2 hover:to-greenxd text-white font-bold text-sm shadow-lg shadow-greenx/25 hover:shadow-greenx/40 hover:scale-[1.02] transition-all">
                                <i data-lucide="user-plus" class="w-3.5 h-3.5 mr-1.5 shrink-0"></i> Criar conta
                            </a>
                            <?php endif; ?>
                            <a href="<?= BASE_PATH ?>/afiliados"
                               class="flex items-center justify-center px-5 py-2.5 rounded-xl bg-white/[0.06] border border-white/[0.08] text-sm font-medium text-zinc-300 hover:text-white hover:border-white/[0.15] transition-all">
                                Saiba mais
                            </a>
                        </div>
                    </div>

                    <div class="grid grid-cols-3 gap-2 sm:gap-5">
                        <div class="text-center p-2 sm:p-5 rounded-xl sm:rounded-2xl bg-white/[0.04] border border-white/[0.06] hover:border-greenx/20 transition-all">
                            <div class="w-8 h-8 sm:w-12 sm:h-12 rounded-lg sm:rounded-xl bg-greenx/15 flex items-center justify-center mx-auto mb-1.5 sm:mb-3">
                                <span class="text-greenx font-black text-sm sm:text-lg">1</span>
                            </div>
                            <p class="text-[10px] sm:text-sm font-bold leading-tight">Cadastre-se</p>
                            <p class="text-[8px] sm:text-[10px] text-zinc-500 mt-0.5 sm:mt-1">Grátis e rápido</p>
                        </div>
                        <div class="text-center p-2 sm:p-5 rounded-xl sm:rounded-2xl bg-white/[0.04] border border-white/[0.06] hover:border-greenx/20 transition-all">
                            <div class="w-8 h-8 sm:w-12 sm:h-12 rounded-lg sm:rounded-xl bg-greenx/15 flex items-center justify-center mx-auto mb-1.5 sm:mb-3">
                                <span class="text-greenx font-black text-sm sm:text-lg">2</span>
                            </div>
                            <p class="text-[10px] sm:text-sm font-bold leading-tight">Compartilhe</p>
                            <p class="text-[8px] sm:text-[10px] text-zinc-500 mt-0.5 sm:mt-1">Seu link</p>
                        </div>
                        <div class="text-center p-2 sm:p-5 rounded-xl sm:rounded-2xl bg-white/[0.04] border border-white/[0.06] hover:border-greenx/20 transition-all">
                            <div class="w-8 h-8 sm:w-12 sm:h-12 rounded-lg sm:rounded-xl bg-greenx/15 flex items-center justify-center mx-auto mb-1.5 sm:mb-3">
                                <span class="text-greenx font-black text-sm sm:text-lg">3</span>
                            </div>
                            <p class="text-[10px] sm:text-sm font-bold leading-tight">Receba</p>
                            <p class="text-[8px] sm:text-[10px] text-zinc-500 mt-0.5 sm:mt-1">Via PIX</p>
                        </div>
                        <div class="col-span-3 grid grid-cols-3 gap-2 sm:gap-3 mt-2">
                            <div class="text-center p-2.5 sm:p-3.5 rounded-xl bg-white/[0.02] border border-white/[0.04]">
                                <div class="text-sm sm:text-base font-bold text-greenx">PIX</div>
                                <div class="text-[8px] sm:text-[9px] text-zinc-500 uppercase tracking-wide">Pagamento</div>
                            </div>
                            <div class="text-center p-2.5 sm:p-3.5 rounded-xl bg-white/[0.02] border border-white/[0.04]">
                                <div class="text-sm sm:text-base font-bold text-greenx"><?= (int)$affRulesHome['cookie_days'] ?>d</div>
                                <div class="text-[8px] sm:text-[9px] text-zinc-500 uppercase tracking-wide">Cookie</div>
                            </div>
                            <div class="text-center p-2.5 sm:p-3.5 rounded-xl bg-white/[0.02] border border-white/[0.04]">
                                <div class="text-sm sm:text-base font-bold text-greenx">Grátis</div>
                                <div class="text-[8px] sm:text-[9px] text-zinc-500 uppercase tracking-wide">Cadastro</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
    <?php endif; ?>

    <!-- =========== FINAL CTA =========== -->
    <?php if (!$isLoggedIn && $q === ''): ?>
    <section class="max-w-7xl mx-auto px-4 sm:px-6 pb-16 sm:pb-20">
        <div class="relative overflow-hidden rounded-3xl border border-white/[0.06] bg-blackx2">
            <div class="absolute top-0 right-0 w-[400px] h-[400px] bg-greenx/[0.10] rounded-full blur-[140px] pointer-events-none"></div>
            <div class="absolute bottom-0 left-0 w-80 h-80 bg-greenxd/[0.06] rounded-full blur-[120px] pointer-events-none"></div>
            <div class="relative p-10 sm:p-12 md:p-16 flex flex-col md:flex-row items-center justify-between gap-10">
                <div>
                    <h2 class="text-3xl sm:text-4xl font-black">Pronto para começar?</h2>
                    <p class="text-zinc-400 mt-3 text-sm sm:text-base leading-relaxed max-w-lg">Crie sua conta gratuita e acesse carteira digital, pagamentos PIX instantâneos e proteção Escrow em cada transação.</p>
                </div>
                <div class="flex flex-col sm:flex-row gap-2 shrink-0 w-full md:w-auto">
                    <a href="<?= BASE_PATH ?>/register"
                       class="flex-1 md:flex-none flex items-center justify-center gap-1.5 px-5 py-2.5 rounded-xl bg-gradient-to-r from-greenx to-greenxd text-white font-bold text-sm shadow-lg shadow-greenx/25 hover:shadow-greenx/40 hover:scale-[1.02] transition-all">
                        <i data-lucide="rocket" class="w-3.5 h-3.5"></i>
                        Criar conta
                    </a>
                    <a href="<?= BASE_PATH ?>/login"
                       class="flex-1 md:flex-none flex items-center justify-center gap-1.5 px-5 py-2.5 rounded-xl bg-white/[0.06] border border-white/[0.08] text-sm font-medium text-zinc-300 hover:text-white hover:border-white/[0.15] transition-all">
                        Já tenho conta
                    </a>
                </div>
            </div>
        </div>
    </section>
    <?php endif; ?>
</div>

<!-- Home page custom styles & animations -->
<style>
/* Hero reveal animation */
.hero-reveal {
    opacity: 0;
    transform: translateY(30px);
    animation: heroReveal 0.8s cubic-bezier(0.22, 1, 0.36, 1) forwards;
}
@keyframes heroReveal {
    to { opacity: 1; transform: translateY(0); }
}

/* Accent text with glow */
.hero-accent-text {
    color: var(--t-accent);
    text-shadow: 0 0 60px rgba(var(--t-accent-rgb), 0.3), 0 0 120px rgba(var(--t-accent-rgb), 0.1);
}

/* Animated orbs */
.hero-orb-1 { animation: orbFloat1 8s ease-in-out infinite; }
.hero-orb-2 { animation: orbFloat2 10s ease-in-out infinite; }
.hero-orb-3 { animation: orbFloat3 12s ease-in-out infinite; }
@keyframes orbFloat1 {
    0%, 100% { transform: translate(0, 0) scale(1); }
    50% { transform: translate(30px, -20px) scale(1.1); }
}
@keyframes orbFloat2 {
    0%, 100% { transform: translate(0, 0) scale(1); }
    50% { transform: translate(-25px, 15px) scale(1.05); }
}
@keyframes orbFloat3 {
    0%, 100% { transform: translate(0, 0) scale(1); }
    50% { transform: translate(20px, 25px) scale(0.95); }
}

/* Scroll indicator */
.scroll-dot {
    animation: scrollDot 2s ease-in-out infinite;
}
@keyframes scrollDot {
    0%, 100% { transform: translateY(0); opacity: 1; }
    50% { transform: translateY(8px); opacity: 0.3; }
}

/* Trust marquee — seamless infinite loop */
.trust-marquee { overflow: hidden; }
.trust-marquee-track {
    animation: marqueeScroll 30s linear infinite;
}
@keyframes marqueeScroll {
    0% { transform: translateX(0); }
    100% { transform: translateX(-100%); }
}
/* Marquee item text color — visible in both themes */
.marquee-item { color: #71717a; }
html.light-mode .marquee-item { color: #6b7280; }

/* Explorar text — visible in dark mode */
.home-explorar-text { color: #a1a1aa; }
html.light-mode .home-explorar-text { color: #4b5563; }

/* Como funciona icon — white SVG on hover */
.group:hover .como-icon .como-icon-svg { color: #fff !important; }
html.light-mode .group:hover .como-icon .como-icon-svg { color: #fff !important; }

/* Category card hover glow */
.cat-card::before {
    content: '';
    position: absolute;
    inset: 0;
    border-radius: inherit;
    opacity: 0;
    transition: opacity 0.5s;
    box-shadow: inset 0 0 60px rgba(var(--t-accent-rgb), 0.08);
    pointer-events: none;
    z-index: 1;
}
.cat-card:hover::before { opacity: 1; }

/* Category name pill — always high contrast */
.cat-name-pill {
    text-shadow: 0 1px 8px rgba(0,0,0,0.8);
}

/* Counter animation */
[data-counter] {
    font-variant-numeric: tabular-nums;
}

/* Hide "A partir de" prefix on small screens to keep price on 1 line */
@media (max-width: 639px) {
    .sf-price-prefix { display: none; }
}
</style>

<script>
// Animated counters
document.addEventListener('DOMContentLoaded', function() {
    const counters = document.querySelectorAll('[data-counter]');
    if (!counters.length) return;
    const observer = new IntersectionObserver(function(entries) {
        entries.forEach(function(entry) {
            if (!entry.isIntersecting) return;
            const el = entry.target;
            const target = parseInt(el.dataset.counter, 10);
            const duration = 2000;
            const start = performance.now();
            function update(now) {
                const elapsed = now - start;
                const progress = Math.min(elapsed / duration, 1);
                const eased = 1 - Math.pow(1 - progress, 3);
                el.textContent = Math.floor(eased * target).toLocaleString('pt-BR') + '+';
                if (progress < 1) requestAnimationFrame(update);
            }
            requestAnimationFrame(update);
            observer.unobserve(el);
        });
    }, { threshold: 0.3 });
    counters.forEach(function(c) { observer.observe(c); });
});
</script>

<?php
include __DIR__ . '/../views/partials/storefront_footer.php';
include __DIR__ . '/../views/partials/footer.php';
