<?php
declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) session_start();

require_once __DIR__ . '/../src/db.php';
require_once __DIR__ . '/../src/storefront.php';

$conn      = (new Database())->connect();
$cartCount = sfCartCount();

$currentPage = 'reembolso';
$pageTitle   = 'Política de Reembolso — Basefy';

include __DIR__ . '/../views/partials/header.php';
include __DIR__ . '/../views/partials/storefront_nav.php';
?>

<style>
    .legal-fade { opacity: 0; transform: translateY(20px); animation: legalFade 0.6s ease forwards; }
    @keyframes legalFade { to { opacity: 1; transform: translateY(0); } }
    .legal-delay-1 { animation-delay: 0.05s; }
    .legal-delay-2 { animation-delay: 0.10s; }

    .legal-section { scroll-margin-top: 100px; }

    .legal-body h3 { font-size: 1.1rem; font-weight: 700; margin: 2rem 0 0.8rem; padding-bottom: 0.5rem; border-bottom: 1px solid rgba(255,255,255,0.06); }
    .legal-body h3:first-child { margin-top: 0; }
    .legal-body p { color: #a1a1aa; font-size: 0.9rem; line-height: 1.8; margin-bottom: 0.8rem; }
    .legal-body ul { list-style: none; padding: 0; margin: 0.5rem 0 1rem; }
    .legal-body ul li { position: relative; padding-left: 1.5rem; color: #a1a1aa; font-size: 0.9rem; line-height: 1.8; margin-bottom: 0.35rem; }
    .legal-body ul li::before { content: ''; position: absolute; left: 0; top: 0.65rem; width: 6px; height: 6px; border-radius: 50%; background: var(--t-accent); opacity: 0.6; }

    .refund-card { background: rgba(var(--t-accent-rgb), 0.04); border: 1px solid rgba(var(--t-accent-rgb), 0.12); border-radius: 16px; padding: 1.5rem; margin-bottom: 1.5rem; }
    .refund-card h4 { font-weight: 700; font-size: 0.95rem; margin-bottom: 0.5rem; display: flex; align-items: center; gap: 0.5rem; }
    .refund-card p { color: #a1a1aa; font-size: 0.85rem; line-height: 1.7; margin: 0; }

    .light-mode .legal-body h3 { border-bottom-color: rgba(0,0,0,0.08); }
    .light-mode .legal-body p,
    .light-mode .legal-body ul li { color: #3f3f46; }
    .light-mode .refund-card { background: rgba(var(--t-accent-rgb), 0.03); border-color: rgba(var(--t-accent-rgb), 0.15); }
    .light-mode .refund-card p { color: #3f3f46; }
</style>

<div class="min-h-screen bg-blackx">

    <!-- Breadcrumb -->
    <div class="max-w-5xl mx-auto px-4 sm:px-6 pt-6 legal-fade">
        <nav class="flex items-center gap-2 text-sm text-zinc-500">
            <a href="/" class="hover:text-greenx transition-colors">Início</a>
            <i data-lucide="chevron-right" class="w-3.5 h-3.5"></i>
            <a href="<?= BASE_PATH ?>/central_ajuda" class="hover:text-greenx transition-colors">Central de Ajuda</a>
            <i data-lucide="chevron-right" class="w-3.5 h-3.5"></i>
            <span class="text-zinc-300">Política de Reembolso</span>
        </nav>
    </div>

    <!-- Hero -->
    <section class="max-w-5xl mx-auto px-4 sm:px-6 pt-8 pb-8 text-center legal-fade legal-delay-1">
        <div class="inline-flex items-center gap-2 px-4 py-1.5 rounded-full bg-greenx/10 border border-greenx/20 text-greenx text-xs font-semibold mb-5">
            <i data-lucide="rotate-ccw" class="w-3.5 h-3.5"></i> Reembolso e Devoluções
        </div>
        <h1 class="text-3xl md:text-4xl font-black tracking-tight">Política de Reembolso</h1>
        <p class="text-zinc-400 mt-3 text-sm max-w-2xl mx-auto">Entenda quando e como você pode solicitar o reembolso de uma compra realizada na plataforma Basefy.</p>
        <p class="text-zinc-600 text-xs mt-2">Última atualização: Março de 2026</p>
    </section>

    <!-- Content -->
    <section class="max-w-5xl mx-auto px-4 sm:px-6 pb-20 legal-fade legal-delay-2">
        <div class="bg-blackx2 border border-white/[0.06] rounded-2xl p-6 md:p-10 legal-body">

            <!-- Situações -->
            <div id="situacoes" class="legal-section">
                <h3>Quando o Reembolso é Aplicável</h3>
                <p>A Basefy tem uma Política de Reembolso clara e, como forma de segurança para nossos clientes, reserva esse direito sempre que ocorrer uma das situações a seguir:</p>
                <ul>
                    <li>O cliente desiste da compra <strong>antes</strong> de receber o produto.</li>
                    <li>O cliente desiste da compra (arrependimento), desde que não utilize e mantenha a integridade do produto nas mesmas condições que recebeu.</li>
                    <li>Caso o vendedor não consiga entregar o produto/serviço.</li>
                    <li>Caso o produto/serviço não esteja de acordo com as informações fornecidas no anúncio.</li>
                    <li>Em situações de imprevisto (não tenha mais estoque do produto, por exemplo).</li>
                    <li>Caso o vendedor não responda ou não retorne contato.</li>
                </ul>
            </div>

            <!-- Métodos de reembolso -->
            <div id="metodos" class="legal-section">
                <h3>Métodos de Reembolso</h3>
                <p>O reembolso é realizado de acordo com o método de pagamento utilizado pelo cliente:</p>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mt-6">
                    <!-- Cartão -->
                    <div class="refund-card">
                        <h4>
                            <span class="w-8 h-8 rounded-lg bg-greenx/10 border border-greenx/20 flex items-center justify-center flex-shrink-0">
                                <i data-lucide="credit-card" class="w-4 h-4 text-purple-400"></i>
                            </span>
                            Cartão de Crédito
                        </h4>
                        <p>O estorno é repassado para a operadora do cartão. Caso a fatura já tenha sido fechada, o comprador recebe como crédito na fatura do mês seguinte. Do contrário, o abatimento do valor ocorre no período em que o reembolso foi pedido.</p>
                    </div>

                    <!-- PIX / Boleto -->
                    <div class="refund-card">
                        <h4>
                            <span class="w-8 h-8 rounded-lg bg-greenx/10 border border-greenx/20 flex items-center justify-center flex-shrink-0">
                                <i data-lucide="qr-code" class="w-4 h-4 text-greenx"></i>
                            </span>
                            PIX / Boleto / Depósito
                        </h4>
                        <p>O reembolso do valor é feito diretamente no pedido da intervenção ou através do site, onde o saldo ficará disponível em forma de crédito. O usuário poderá comprar qualquer produto/serviço ou retirar o saldo para sua conta bancária. Pagamentos via PIX serão reembolsados para a conta de origem.</p>
                    </div>

                    <!-- Cripto -->
                    <div class="refund-card">
                        <h4>
                            <span class="w-8 h-8 rounded-lg bg-orange-500/10 border border-orange-500/20 flex items-center justify-center flex-shrink-0">
                                <i data-lucide="bitcoin" class="w-4 h-4 text-orange-400"></i>
                            </span>
                            Criptomoeda
                        </h4>
                        <p>O reembolso do valor é feito diretamente no pedido da intervenção de forma manual pelo moderador para a carteira de origem.</p>
                    </div>
                </div>
            </div>

            <!-- Prazos -->
            <div id="prazos" class="legal-section">
                <h3>Prazos e Processamento</h3>

                <div class="flex items-center gap-4 p-5 rounded-2xl bg-amber-500/[0.06] border border-amber-500/15 mb-6">
                    <div class="w-12 h-12 rounded-xl bg-amber-500/10 border border-amber-500/20 flex items-center justify-center flex-shrink-0">
                        <i data-lucide="clock" class="w-6 h-6 text-amber-400"></i>
                    </div>
                    <div>
                        <p class="text-sm font-bold text-amber-300 mb-0.5">Prazo de processamento</p>
                        <p class="text-xs text-zinc-400 !mb-0">O reembolso é processado em <strong class="text-white">até 48 HORAS ÚTEIS</strong>, após ser autorizado por um moderador.</p>
                    </div>
                </div>

                <p>A data limite para <strong>SOLICITAÇÃO DE REEMBOLSO</strong> nas situações mencionadas acima será informada no chat no início da compra, que coincide com a data de liberação para o vendedor retirar o dinheiro. Consideramos que este prazo é mais que suficiente para o usuário conferir a integridade do produto adquirido.</p>
                <p>Caso haja problemas com a compra após o prazo limite, o reembolso será de responsabilidade do vendedor. Para mais informações sobre reembolsos, devoluções e intervenções, consulte nossos <a href="<?= BASE_PATH ?>/termos" class="text-greenx hover:underline font-medium">Termos de Uso</a>.</p>
            </div>

            <!-- Garantia de Entrega -->
            <div id="garantia" class="legal-section">
                <h3>Política de Garantia de Entrega</h3>
                <p>A Basefy assegura exclusivamente a entrega dos produtos ou serviços comprados em nossa plataforma. Se a entrega não ocorrer conforme o acordado e estiver em conformidade com nossa política de reembolso, procederemos ao cancelamento da transação e à devolução do pagamento ao comprador.</p>
                <p>É responsabilidade do comprador notificar a Basefy imediatamente por meio da página do pedido (solicitando intervenções ou relatando problemas) se o produto não for entregue dentro do prazo estabelecido para reembolso, que coincide com o prazo de liberação do pagamento ao vendedor — o prazo que o dinheiro será disponibilizado ao vendedor aparece através de uma mensagem do sistema no início do chat da compra.</p>
                <p>Na ausência dessa notificação dentro do prazo estabelecido, a Basefy considerará que a entrega do produto foi realizada com sucesso. Neste caso, reclamações posteriores não se qualificam para a política de Entrega Garantida. No entanto, isso não isenta o vendedor de suas responsabilidades quanto ao que foi anunciado e entregue.</p>
            </div>

            <!-- CTA -->
            <div class="mt-10 p-6 rounded-2xl bg-gradient-to-br from-greenx/[0.08] to-transparent border border-greenx/15 text-center">
                <h4 class="text-lg font-bold mb-2">Precisa de ajuda com um reembolso?</h4>
                <p class="text-sm text-zinc-400 mb-4 !mb-4">Nossa equipe de suporte está pronta para ajudar. Abra um ticket e resolveremos o mais rápido possível.</p>
                <a href="<?= BASE_PATH ?>/tickets_novo" class="inline-flex items-center gap-2 px-6 py-2.5 rounded-xl bg-gradient-to-r from-greenx to-greenxd text-white text-sm font-bold shadow-lg shadow-greenx/20 hover:from-greenx2 hover:to-greenxd transition-all">
                    <i data-lucide="message-circle" class="w-4 h-4"></i> Abrir Ticket de Suporte
                </a>
            </div>

        </div>
    </section>
</div>

<?php
include __DIR__ . '/../views/partials/storefront_footer.php';
include __DIR__ . '/../views/partials/footer.php';
?>
