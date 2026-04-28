<?php
declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) session_start();

require_once __DIR__ . '/../src/db.php';
require_once __DIR__ . '/../src/storefront.php';

$userId     = (int)($_SESSION['user_id'] ?? 0);
$userRole   = (string)($_SESSION['user']['role'] ?? 'usuario');
$isLoggedIn = $userId > 0;
$conn       = (new Database())->connect();
$cartCount  = sfCartCount();

$currentPage = 'como_funciona';
$pageTitle   = 'Como Funciona — Basefy';

include __DIR__ . '/../views/partials/header.php';
include __DIR__ . '/../views/partials/storefront_nav.php';
?>

<style>
    .cf-glow { position: relative; }
    .cf-glow::before {
        content:''; position:absolute; inset:-1px; border-radius:inherit;
        background:linear-gradient(135deg, rgba(var(--t-accent-rgb),0.3), transparent 60%);
        z-index:-1; opacity:0; transition:opacity 0.4s;
    }
    .cf-glow:hover::before { opacity:1; }

    .cf-step-line { position:relative; }
    .cf-step-line::after {
        content:''; position:absolute; left:50%; top:100%; width:2px; height:40px;
        background:linear-gradient(to bottom, rgba(var(--t-accent-rgb),0.4), transparent);
    }
    .cf-step-line:last-child::after { display:none; }

    .cf-float { animation: cfFloat 6s ease-in-out infinite; }
    @keyframes cfFloat {
        0%,100% { transform:translateY(0); }
        50%     { transform:translateY(-8px); }
    }

    .cf-fade-in { opacity:0; transform:translateY(20px); animation:cfFadeIn 0.6s ease forwards; }
    @keyframes cfFadeIn { to { opacity:1; transform:translateY(0); } }
    .cf-delay-1 { animation-delay:0.1s; }
    .cf-delay-2 { animation-delay:0.2s; }
    .cf-delay-3 { animation-delay:0.3s; }
    .cf-delay-4 { animation-delay:0.4s; }
    .cf-delay-5 { animation-delay:0.5s; }
    .cf-delay-6 { animation-delay:0.6s; }

    .cf-card {
        backdrop-filter: blur(12px);
        -webkit-backdrop-filter: blur(12px);
    }
</style>

<div class="min-h-screen bg-blackx">

    <!-- =========== HERO =========== -->
    <section class="relative overflow-hidden">
        <div class="absolute inset-0 bg-[radial-gradient(ellipse_60%_50%_at_50%_-10%,rgba(var(--t-accent-rgb),0.12),transparent)]"></div>
        <div class="absolute top-0 left-1/2 -translate-x-1/2 w-[700px] h-[700px] bg-greenx/[0.04] rounded-full blur-[140px] pointer-events-none"></div>

        <div class="relative max-w-[1440px] mx-auto px-4 sm:px-6 py-16 sm:py-20 lg:py-24 text-center cf-fade-in">
            <div class="inline-flex items-center gap-2 px-4 py-1.5 rounded-full bg-greenx/10 border border-greenx/20 text-greenx text-xs font-semibold mb-6">
                <i data-lucide="info" class="w-3.5 h-3.5"></i> Guia da Plataforma
            </div>
            <h1 class="text-3xl sm:text-4xl lg:text-5xl font-black tracking-tight leading-[1.15]">
                Como <span class="bg-gradient-to-r from-greenx to-greenx2 bg-clip-text text-transparent">funciona?</span>
            </h1>
            <p class="mt-4 text-zinc-400 text-sm sm:text-base lg:text-lg max-w-2xl mx-auto leading-relaxed">
                Entenda o processo completo de comprar e vender na nossa plataforma.<br class="hidden sm:block">
                Segurança, transparência e facilidade do início ao fim.
            </p>
        </div>
    </section>

    <!-- =========== TABS: Comprador / Vendedor =========== -->
    <section class="max-w-[1440px] mx-auto px-4 sm:px-6 pb-20">

        <!-- ═══ PROCESSO UNIFICADO ═══ -->
            <div class="grid gap-8 md:gap-6">

                <!-- Step 1 -->
                <div class="cf-fade-in cf-delay-1">
                    <div class="flex flex-col md:flex-row items-start gap-5 bg-blackx2/80 border border-blackx3 rounded-2xl p-6 md:p-8 cf-card cf-glow hover:border-greenx/30 transition-all">
                        <div class="flex-shrink-0 w-14 h-14 rounded-2xl bg-gradient-to-br from-greenx/20 to-greenx/5 border border-greenx/20 flex items-center justify-center cf-float">
                            <span class="text-greenx font-black text-xl">1</span>
                        </div>
                        <div class="flex-1">
                            <div class="flex items-center gap-3 mb-2">
                                <i data-lucide="user-plus" class="w-5 h-5 text-greenx"></i>
                                <h3 class="text-lg font-bold">Crie sua conta gratuitamente</h3>
                            </div>
                            <p class="text-zinc-400 text-sm leading-relaxed">
                                Registre-se em poucos segundos. Basta informar nome, e-mail e criar uma senha. 
                                Você também pode usar o Google para login rápido. Com uma única conta, você compra e vende na plataforma.
                            </p>
                            <div class="mt-4 flex flex-wrap gap-2">
                                <span class="inline-flex items-center gap-1.5 text-xs bg-greenx/10 text-greenx px-3 py-1.5 rounded-lg"><i data-lucide="zap" class="w-3 h-3"></i> Cadastro rápido</span>
                                <span class="inline-flex items-center gap-1.5 text-xs bg-greenx/10 text-greenx px-3 py-1.5 rounded-lg"><i data-lucide="shield-check" class="w-3 h-3"></i> 100% gratuito</span>
                                <span class="inline-flex items-center gap-1.5 text-xs bg-greenx/10 text-greenx px-3 py-1.5 rounded-lg"><i data-lucide="repeat" class="w-3 h-3"></i> Compre e venda com a mesma conta</span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="flex justify-center"><div class="w-px h-8 bg-gradient-to-b from-greenx/30 to-transparent"></div></div>

                <!-- Step 2 -->
                <div class="cf-fade-in cf-delay-2">
                    <div class="flex flex-col md:flex-row items-start gap-5 bg-blackx2/80 border border-blackx3 rounded-2xl p-6 md:p-8 cf-card cf-glow hover:border-greenx/30 transition-all">
                        <div class="flex-shrink-0 w-14 h-14 rounded-2xl bg-gradient-to-br from-greenx/20 to-greenx/5 border border-greenx/20 flex items-center justify-center cf-float" style="animation-delay:0.5s">
                            <span class="text-greenx font-black text-xl">2</span>
                        </div>
                        <div class="flex-1">
                            <div class="flex items-center gap-3 mb-2">
                                <i data-lucide="search" class="w-5 h-5 text-greenx"></i>
                                <h3 class="text-lg font-bold">Explore ou cadastre produtos</h3>
                            </div>
                            <p class="text-zinc-400 text-sm leading-relaxed">
                                <strong class="text-white">Quer comprar?</strong> Navegue pelo catálogo por categorias, pesquise por nome ou explore as lojas. 
                                Cada produto tem descrição completa, fotos e avaliações de outros compradores.<br>
                                <strong class="text-white">Quer vender?</strong> Acesse o painel e cadastre seus produtos com descrição, imagens e preço. 
                                Suportamos <strong class="text-zinc-300">Produto</strong>, <strong class="text-zinc-300">Dinâmico</strong> (variantes com preços diferentes) e <strong class="text-zinc-300">Serviço</strong>.
                            </p>
                            <div class="mt-4 flex flex-wrap gap-2">
                                <span class="inline-flex items-center gap-1.5 text-xs bg-greenx/10 text-greenx px-3 py-1.5 rounded-lg"><i data-lucide="grid-3x3" class="w-3 h-3"></i> Categorias organizadas</span>
                                <span class="inline-flex items-center gap-1.5 text-xs bg-greenx/10 text-greenx px-3 py-1.5 rounded-lg"><i data-lucide="star" class="w-3 h-3"></i> Avaliações reais</span>
                                <span class="inline-flex items-center gap-1.5 text-xs bg-greenx/10 text-greenx px-3 py-1.5 rounded-lg"><i data-lucide="package-plus" class="w-3 h-3"></i> Painel de vendedor</span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="flex justify-center"><div class="w-px h-8 bg-gradient-to-b from-greenx/30 to-transparent"></div></div>

                <!-- Step 3 -->
                <div class="cf-fade-in cf-delay-3">
                    <div class="flex flex-col md:flex-row items-start gap-5 bg-blackx2/80 border border-blackx3 rounded-2xl p-6 md:p-8 cf-card cf-glow hover:border-greenx/30 transition-all">
                        <div class="flex-shrink-0 w-14 h-14 rounded-2xl bg-gradient-to-br from-greenx/20 to-greenx/5 border border-greenx/20 flex items-center justify-center cf-float" style="animation-delay:1s">
                            <span class="text-greenx font-black text-xl">3</span>
                        </div>
                        <div class="flex-1">
                            <div class="flex items-center gap-3 mb-2">
                                <i data-lucide="credit-card" class="w-5 h-5 text-greenx"></i>
                                <h3 class="text-lg font-bold">Pague ou receba com PIX instantâneo</h3>
                            </div>
                            <p class="text-zinc-400 text-sm leading-relaxed">
                                As transações são feitas via PIX com confirmação automática em segundos. 
                                O dinheiro fica protegido pelo sistema de <strong class="text-greenx">escrow</strong> — 
                                o vendedor só recebe após a confirmação da entrega pelo comprador.
                            </p>
                            <div class="mt-4 flex flex-wrap gap-2">
                                <span class="inline-flex items-center gap-1.5 text-xs bg-greenx/10 text-greenx px-3 py-1.5 rounded-lg"><i data-lucide="qr-code" class="w-3 h-3"></i> PIX QR Code</span>
                                <span class="inline-flex items-center gap-1.5 text-xs bg-greenx/10 text-greenx px-3 py-1.5 rounded-lg"><i data-lucide="lock" class="w-3 h-3"></i> Escrow seguro</span>
                                <span class="inline-flex items-center gap-1.5 text-xs bg-greenx/10 text-greenx px-3 py-1.5 rounded-lg"><i data-lucide="clock" class="w-3 h-3"></i> Confirmação automática</span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="flex justify-center"><div class="w-px h-8 bg-gradient-to-b from-greenx/30 to-transparent"></div></div>

                <!-- Step 4 -->
                <div class="cf-fade-in cf-delay-4">
                    <div class="flex flex-col md:flex-row items-start gap-5 bg-blackx2/80 border border-blackx3 rounded-2xl p-6 md:p-8 cf-card cf-glow hover:border-greenx/30 transition-all">
                        <div class="flex-shrink-0 w-14 h-14 rounded-2xl bg-gradient-to-br from-greenx/20 to-greenx/5 border border-greenx/20 flex items-center justify-center cf-float" style="animation-delay:1.5s">
                            <span class="text-greenx font-black text-xl">4</span>
                        </div>
                        <div class="flex-1">
                            <div class="flex items-center gap-3 mb-2">
                                <i data-lucide="message-circle" class="w-5 h-5 text-greenx"></i>
                                <h3 class="text-lg font-bold">Acompanhe e converse pelo chat</h3>
                            </div>
                            <p class="text-zinc-400 text-sm leading-relaxed">
                                Após a compra, use o chat integrado para se comunicar diretamente. 
                                Combine detalhes da entrega, tire dúvidas e acompanhe o progresso do pedido em tempo real 
                                pela página "Meus Pedidos".
                            </p>
                            <div class="mt-4 flex flex-wrap gap-2">
                                <span class="inline-flex items-center gap-1.5 text-xs bg-greenx/10 text-greenx px-3 py-1.5 rounded-lg"><i data-lucide="message-square" class="w-3 h-3"></i> Chat em tempo real</span>
                                <span class="inline-flex items-center gap-1.5 text-xs bg-greenx/10 text-greenx px-3 py-1.5 rounded-lg"><i data-lucide="truck" class="w-3 h-3"></i> Acompanhamento do pedido</span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="flex justify-center"><div class="w-px h-8 bg-gradient-to-b from-greenx/30 to-transparent"></div></div>

                <!-- Step 5 -->
                <div class="cf-fade-in cf-delay-5">
                    <div class="flex flex-col md:flex-row items-start gap-5 bg-blackx2/80 border border-blackx3 rounded-2xl p-6 md:p-8 cf-card cf-glow hover:border-greenx/30 transition-all">
                        <div class="flex-shrink-0 w-14 h-14 rounded-2xl bg-gradient-to-br from-greenx/20 to-greenx/5 border border-greenx/20 flex items-center justify-center cf-float" style="animation-delay:2s">
                            <span class="text-greenx font-black text-xl">5</span>
                        </div>
                        <div class="flex-1">
                            <div class="flex items-center gap-3 mb-2">
                                <i data-lucide="check-circle-2" class="w-5 h-5 text-greenx"></i>
                                <h3 class="text-lg font-bold">Confirme, avalie e receba</h3>
                            </div>
                            <p class="text-zinc-400 text-sm leading-relaxed">
                                <strong class="text-white">Comprou?</strong> Confirme a entrega no sistema e deixe uma avaliação para ajudar a comunidade.<br>
                                <strong class="text-white">Vendeu?</strong> Quando o comprador confirma, o valor é liberado para sua 
                                <strong class="text-greenx">carteira digital</strong>. Saque via PIX a qualquer momento.
                            </p>
                            <div class="mt-4 flex flex-wrap gap-2">
                                <span class="inline-flex items-center gap-1.5 text-xs bg-greenx/10 text-greenx px-3 py-1.5 rounded-lg"><i data-lucide="thumbs-up" class="w-3 h-3"></i> Confirme a entrega</span>
                                <span class="inline-flex items-center gap-1.5 text-xs bg-greenx/10 text-greenx px-3 py-1.5 rounded-lg"><i data-lucide="star" class="w-3 h-3"></i> Avalie o vendedor</span>
                                <span class="inline-flex items-center gap-1.5 text-xs bg-greenx/10 text-greenx px-3 py-1.5 rounded-lg"><i data-lucide="wallet" class="w-3 h-3"></i> Saque via PIX</span>
                            </div>
                        </div>
                    </div>
                </div>

            </div>

    </section>

    <!-- =========== ESCROW EXPLAINED =========== -->
    <section class="relative overflow-hidden border-t border-white/[0.04]">
        <div class="absolute inset-0 bg-[radial-gradient(ellipse_80%_50%_at_50%_120%,rgba(var(--t-accent-rgb),0.06),transparent)]"></div>
        <div class="relative max-w-[1440px] mx-auto px-4 sm:px-6 py-16 sm:py-20">
            <div class="text-center mb-12">
                <div class="inline-flex items-center gap-2 px-4 py-1.5 rounded-full bg-greenx/10 border border-greenx/20 text-greenx text-xs font-semibold mb-4">
                    <i data-lucide="shield-check" class="w-3.5 h-3.5"></i> Segurança Garantida
                </div>
                <h2 class="text-2xl sm:text-3xl font-black tracking-tight">
                    Como funciona o <span class="text-greenx">Escrow</span>?
                </h2>
                <p class="mt-3 text-zinc-400 text-sm max-w-xl mx-auto">
                    O sistema de escrow protege tanto o comprador quanto o vendedor. Veja o fluxo completo:
                </p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <!-- Escrow Step 1 -->
                <div class="relative bg-blackx2/80 border border-blackx3 rounded-2xl p-6 text-center cf-card hover:border-greenx/30 transition-all group">
                    <div class="w-16 h-16 mx-auto rounded-2xl bg-gradient-to-br from-greenx/20 to-greenxd/5 border border-greenx/20 flex items-center justify-center mb-4 group-hover:scale-110 transition-transform">
                        <i data-lucide="credit-card" class="w-7 h-7 text-purple-400"></i>
                    </div>
                    <h3 class="font-bold mb-2">Comprador paga</h3>
                    <p class="text-zinc-500 text-xs leading-relaxed">
                        O pagamento via PIX é confirmado automaticamente e o valor fica <strong class="text-zinc-300">retido em custódia</strong> segura.
                    </p>
                    <div class="hidden md:block absolute top-1/2 -right-3 -translate-y-1/2 text-greenx/40 z-10">
                        <i data-lucide="chevron-right" class="w-6 h-6"></i>
                    </div>
                </div>

                <!-- Escrow Step 2 -->
                <div class="relative bg-blackx2/80 border border-blackx3 rounded-2xl p-6 text-center cf-card hover:border-greenx/30 transition-all group">
                    <div class="w-16 h-16 mx-auto rounded-2xl bg-gradient-to-br from-amber-500/20 to-amber-500/5 border border-amber-500/20 flex items-center justify-center mb-4 group-hover:scale-110 transition-transform">
                        <i data-lucide="package" class="w-7 h-7 text-amber-400"></i>
                    </div>
                    <h3 class="font-bold mb-2">Vendedor entrega</h3>
                    <p class="text-zinc-500 text-xs leading-relaxed">
                        O vendedor envia o produto/serviço ao comprador via chat ou conforme combinado na plataforma.
                    </p>
                    <div class="hidden md:block absolute top-1/2 -right-3 -translate-y-1/2 text-greenx/40 z-10">
                        <i data-lucide="chevron-right" class="w-6 h-6"></i>
                    </div>
                </div>

                <!-- Escrow Step 3 -->
                <div class="bg-blackx2/80 border border-blackx3 rounded-2xl p-6 text-center cf-card hover:border-greenx/30 transition-all group">
                    <div class="w-16 h-16 mx-auto rounded-2xl bg-gradient-to-br from-greenx/20 to-greenx/5 border border-greenx/20 flex items-center justify-center mb-4 group-hover:scale-110 transition-transform">
                        <i data-lucide="check-circle-2" class="w-7 h-7 text-greenx"></i>
                    </div>
                    <h3 class="font-bold mb-2">Dinheiro liberado</h3>
                    <p class="text-zinc-500 text-xs leading-relaxed">
                        Comprador confirma o recebimento e o valor é <strong class="text-greenx">liberado automaticamente</strong> para a carteira do vendedor.
                    </p>
                </div>
            </div>
        </div>
    </section>

    <!-- =========== FAQ =========== -->
    <section class="max-w-[1440px] mx-auto px-4 sm:px-6 py-16 sm:py-20">
        <div class="text-center mb-10">
            <h2 class="text-2xl sm:text-3xl font-black tracking-tight">Perguntas <span class="text-greenx">frequentes</span></h2>
        </div>

        <div class="space-y-3" x-data="{ open: null }">
            <?php
            $faqs = [
                ['q' => 'Preciso pagar para me cadastrar?', 'a' => 'Não. O cadastro é 100% gratuito tanto para compradores quanto para vendedores. A plataforma cobra apenas uma pequena taxa sobre as vendas realizadas.'],
                ['q' => 'Quais formas de pagamento são aceitas?', 'a' => 'Atualmente aceitamos pagamento via PIX com confirmação automática. É rápido, seguro e sem taxas adicionais para o comprador.'],
                ['q' => 'O que acontece se eu não receber o produto?', 'a' => 'Seu dinheiro fica protegido pelo sistema de escrow. Se o vendedor não entregar, o valor permanece retido e pode ser devolvido. Entre em contato com o suporte caso tenha problemas.'],
                ['q' => 'Como faço para sacar meus ganhos?', 'a' => 'Os valores recebidos ficam na sua carteira digital. Você pode solicitar saque via PIX para sua conta bancária a qualquer momento pelo painel do vendedor.'],
                ['q' => 'Posso vender qualquer tipo de produto?', 'a' => 'A plataforma é focada em produtos digitais, contas, gift cards, itens de jogos e serviços digitais. Todos os anúncios passam por moderação para garantir a qualidade.'],
                ['q' => 'O que é produto "Dinâmico"?', 'a' => 'É um produto com múltiplas variantes (opções) que podem ter preços e quantidades diferentes. Ideal para vender planos, pacotes ou variações de um mesmo item.'],
            ];
            foreach ($faqs as $i => $faq): ?>
            <div class="bg-blackx2/80 border border-blackx3 rounded-2xl overflow-hidden transition-all hover:border-greenx/20">
                <button @click="open === <?= $i ?> ? open = null : open = <?= $i ?>" class="w-full flex items-center justify-between px-5 py-4 text-left">
                    <span class="text-sm font-semibold pr-4"><?= htmlspecialchars($faq['q']) ?></span>
                    <i data-lucide="chevron-down" class="w-4 h-4 text-zinc-500 flex-shrink-0 transition-transform duration-300" :class="open === <?= $i ?> && 'rotate-180 text-greenx'"></i>
                </button>
                <div x-show="open === <?= $i ?>" x-collapse x-cloak>
                    <div class="px-5 pb-4 text-sm text-zinc-400 leading-relaxed border-t border-blackx3 pt-3">
                        <?= htmlspecialchars($faq['a']) ?>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </section>

    <!-- =========== CTA =========== -->
    <section class="relative overflow-hidden border-t border-white/[0.04]">
        <div class="absolute inset-0 bg-[radial-gradient(ellipse_60%_50%_at_50%_100%,rgba(var(--t-accent-rgb),0.08),transparent)]"></div>
        <div class="relative max-w-[1440px] mx-auto px-4 sm:px-6 py-16 sm:py-20 text-center">
            <h2 class="text-2xl sm:text-3xl font-black tracking-tight mb-4">
                Pronto para <span class="text-greenx">começar</span>?
            </h2>
            <p class="text-zinc-400 text-sm mb-8 max-w-lg mx-auto">
                Crie sua conta agora e comece a comprar ou vender com total segurança e praticidade.
            </p>
            <div class="flex flex-col sm:flex-row items-center justify-center gap-3">
                <a href="<?= BASE_PATH ?>/register"
                   class="inline-flex items-center gap-2.5 px-8 py-3.5 rounded-2xl bg-gradient-to-r from-greenx to-greenxd text-white font-bold text-sm shadow-xl shadow-greenx/25 hover:shadow-greenx/40 hover:from-greenx2 hover:to-greenxd transition-all">
                    <i data-lucide="rocket" class="w-4.5 h-4.5"></i> Criar minha conta
                </a>
                <a href="<?= BASE_PATH ?>/categorias"
                   class="inline-flex items-center gap-2.5 px-8 py-3.5 rounded-2xl border border-white/[0.1] text-zinc-300 font-semibold text-sm hover:border-greenx/40 hover:text-white transition-all">
                    <i data-lucide="eye" class="w-4 h-4"></i> Explorar catálogo
                </a>
            </div>
        </div>
    </section>

</div>

<?php
include __DIR__ . '/../views/partials/footer.php';
?>
