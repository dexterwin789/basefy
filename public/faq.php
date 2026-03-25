<?php
declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) session_start();

require_once __DIR__ . '/../src/db.php';
require_once __DIR__ . '/../src/storefront.php';

$conn      = (new Database())->connect();
$cartCount = sfCartCount();

$currentPage = 'faq';
$pageTitle   = 'Perguntas Frequentes (FAQ)';

include __DIR__ . '/../views/partials/header.php';
include __DIR__ . '/../views/partials/storefront_nav.php';

$topics = [
    'plataforma' => [
        'label' => 'Basefy',
        'icon'  => 'shield-check',
        'items' => [
            [
                'q' => 'O Basefy é confiável?',
                'a' => 'Definitivamente sim. Entendemos que negociar com desconhecidos pode ser algo nebuloso e é por isso que surgiu o Basefy. O Basefy se tornou um marketplace de referência e milhares de usuários confiam e utilizam a plataforma. Estamos aqui para intermediar os pagamentos e garantir a segurança das transações.',
            ],
            [
                'q' => 'Resumidamente, como funciona o Basefy?',
                'a' => 'Nós somos uma plataforma que intermedia pagamentos, garantindo que o comprador receba o produto e que o vendedor receba pela sua venda. Vamos cuidar para que tudo ocorra com segurança e praticidade.',
            ],
            [
                'q' => 'O Basefy é proprietário dos anúncios?',
                'a' => 'Não. Nós somos uma plataforma que disponibiliza o espaço de nosso site para o vendedor cadastrar seu produto/serviço e vendê-lo para o comprador.',
            ],
        ],
    ],
    'comprador' => [
        'label' => 'Comprador',
        'icon'  => 'shopping-bag',
        'items' => [
            [
                'q' => 'Como comprar um produto/serviço?',
                'a' => 'Basta ir no anúncio desejado e clicar em "Comprar". Você será direcionado para a tela de Checkout, onde poderá revisar as informações do anúncio, escolher planos de segurança e selecionar a melhor forma de pagamento para você. Em nosso blog fizemos um <a href="/blog" class="text-greenx hover:underline">Guia completo para comprar e vender contas de jogos com segurança</a>.',
            ],
            [
                'q' => 'Como recebo meu produto/serviço?',
                'a' => 'Após a confirmação do pagamento, nós iremos liberar um chat de mensagens que conecta você ao vendedor. O vendedor irá conversar com você para te entregar o produto ou serviço. Caso o anúncio tenha <strong>Entrega Automática</strong>, o produto será entregue de forma imediata no chat do pedido, sem a necessidade do vendedor.',
            ],
            [
                'q' => 'O que acontece se eu não receber o produto?',
                'a' => 'Caso você não receba o produto/serviço, não consiga entrar em contato com o vendedor ou o produto/serviço estejam em desacordo com o que foi anunciado basta você clicar no botão "Tenho um problema". Nós iremos rapidamente agir para analisar o problema e resolvê-lo da melhor forma.',
            ],
            [
                'q' => 'O produto/serviço está em desacordo com o anúncio, e agora?',
                'a' => 'Caso o produto/serviço não esteja de acordo com a descrição e informações do anúncio, você pode estar abrindo uma reclamação no botão "problema com a Compra", logo acima do Chat. Assim, nossos moderadores irão intervir para solucionar o problema conforme nossa <a href="/reembolso" class="text-greenx hover:underline">Política de Reembolso</a>.',
            ],
            [
                'q' => 'O vendedor está querendo vender por fora da plataforma, e agora?',
                'a' => 'Nunca aceite efetuar o pagamento por fora da plataforma, pois você pode ser roubado e perder todo seu dinheiro. O Basefy existe justamente para garantir a segurança tanto do comprador quanto do vendedor e que a compra seja concretizada e devidamente entregue. Encorajamos você a denunciar este tipo de atitude.',
            ],
        ],
    ],
    'pagamento' => [
        'label' => 'Pagamento',
        'icon'  => 'credit-card',
        'items' => [
            [
                'q' => 'O pagamento é seguro?',
                'a' => 'Sim, o Basefy assegura o seu pagamento, pois somente repassa o dinheiro ao Vendedor caso ele entregue o produto/serviço de acordo com nossa <a href="/reembolso" class="text-greenx hover:underline">Política de Reembolso</a>.',
            ],
            [
                'q' => 'O pagamento é feito direto para o vendedor?',
                'a' => 'Não. O pagamento é feito diretamente para o Basefy, que é o intermediador da venda e garante segurança para o Comprador.',
            ],
            [
                'q' => 'Quais são as formas de pagamento?',
                'a' => 'Aceitamos PIX (instantâneo), Cartão de Crédito, Boleto Bancário, Criptomoedas e Saldo Basefy. Confira mais detalhes na página <a href="/como_funciona" class="text-greenx hover:underline">Como Funciona</a>.',
            ],
            [
                'q' => 'Qual é o prazo para a aprovação de pagamentos?',
                'a' => 'Boletos Bancários demora 1 dia útil para serem compensado. Já compras feitas por cartão de crédito demoram de 10 minutos a 1 dia útil, pois dependem de uma análise de segurança para serem aprovadas. Pagamentos via criptomoedas podem demorar até 2 horas, dependendo da rede escolhida. PIX e Saldo Basefy são formas de pagamentos instantâneas.',
            ],
            [
                'q' => 'Já paguei, o que eu faço agora?',
                'a' => 'Agradecemos a confiança em nossa Plataforma. Assim que seu pagamento for aprovado, você poderá conferir seu pedido em <a href="/meus_pedidos" class="text-greenx hover:underline">Minhas Compras</a>. Nela você conseguirá conversar com o vendedor através do chat do pedido.',
            ],
        ],
    ],
    'vendedor' => [
        'label' => 'Vendedor',
        'icon'  => 'store',
        'items' => [
            [
                'q' => 'Como anunciar o meu produto/serviço?',
                'a' => 'Para anunciar basta você criar uma conta na Plataforma do Basefy e clicar em <a href="/vendedor/produtos_form" class="text-greenx hover:underline">Anunciar</a>. Você irá preencher um breve formulário com informações do seu produto/serviço, escolher a categoria em que ele se encaixa e ainda poderá adicionar imagens. Em nosso <a href="/blog" class="text-greenx hover:underline">Blog</a> você pode conferir 5 dicas para vender sua conta de jogo online rapidamente.',
            ],
            [
                'q' => 'Quanto tempo leva para que meu anúncio seja aprovado?',
                'a' => 'Visando a qualidade de nossa plataforma, todos os anúncios passam por um filtro que verifica se todas as informações estão de acordo com as normas de nosso website. A aprovação geralmente é rápida, mas poderá demorar até 6 horas para ser aprovada. Caso seu anúncio seja reprovado, você deverá revisar as informações nele contidas.',
            ],
            [
                'q' => 'Quais são as taxas cobradas pelo Basefy?',
                'a' => 'Nós possuímos 3 planos (Prata, Ouro e Diamante) para que você escolha o melhor custo-benefício para sua venda. Confira os detalhes nos nossos <a href="/termos" class="text-greenx hover:underline">Termos de Uso</a>, seção Gratuidade e Cobrança.',
            ],
            [
                'q' => 'Como eu vou fazer para entregar o produto/serviço ao comprador?',
                'a' => 'Após a confirmação do pagamento, nós iremos liberar um chat de mensagens que conecta você ao comprador. No chat você poderá combinar o serviço ou entregar o produto para seu comprador. Lembre-se de oferecer uma ótima experiência de compra para seu cliente, pois ao final ele irá fazer uma avaliação sobre seus serviços/produto. Você também pode configurar seu anúncio com a ferramenta <strong>Entrega Automática</strong>, assim poderá disponibilizar seu produto para ser entregue assim que o pagamento for aprovado.',
            ],
            [
                'q' => 'Quanto tempo leva para eu receber o dinheiro?',
                'a' => 'Em cada categoria/jogo existem especificidades que podem oferecer riscos e/ou recuperação de Itens, por causa disso o prazo de liberação do dinheiro depende do produto/serviço vendido. Damos este prazo para garantir a segurança do pagamento tanto para o vendedor quanto para o comprador. Confira os detalhes nos nossos <a href="/termos" class="text-greenx hover:underline">Termos de Uso</a>.',
            ],
            [
                'q' => 'Como vou receber o meu dinheiro?',
                'a' => 'Após sua venda e decorrido o prazo para liberação, você poderá clicar no menu <a href="/vendedor/saques" class="text-greenx hover:underline">Minhas Retiradas</a> ou apenas em "Retirar" no menu principal. Assim, conseguirá retirar seu dinheiro para sua conta bancária/PIX desde que sua conta esteja 100% verificada.',
            ],
            [
                'q' => 'Meu comprador está querendo comprar por fora da plataforma, e agora?',
                'a' => 'Nunca aceite efetuar o pagamento por fora da plataforma, pois o comprador pode reaver o dinheiro, solicitar chargeback e você pode perder seu produto/serviço, perdendo todo seu dinheiro. O Basefy existe justamente para garantir a segurança tanto do comprador quanto do vendedor para que a compra seja concretizada, devidamente entregue e que você receba pela sua venda. Encorajamos você a denunciar este tipo de atitude.',
            ],
        ],
    ],
    'reembolso' => [
        'label' => 'Reembolso',
        'icon'  => 'refresh-cw',
        'items' => [
            [
                'q' => 'Quando posso pedir um reembolso de uma compra na plataforma?',
                'a' => 'Temos uma <a href="/reembolso" class="text-greenx hover:underline">Política de Reembolso</a> onde listamos os casos em que é cabível solicitar o reembolso do pedido.',
            ],
            [
                'q' => 'Minha compra já foi entregue, posso pedir o reembolso?',
                'a' => 'Em casos em que a compra foi confirmada ou o produto já foi enviado, você poderá conversar com o vendedor para tentar devolver o produto e pedir o reembolso. Caso não encontrem um acordo, nós iremos intermediar a situação norteados pela nossa <a href="/reembolso" class="text-greenx hover:underline">Política de Reembolso</a> visando resolver o problema da melhor forma possível.',
            ],
            [
                'q' => 'Qual o prazo para o reembolso para compras realizadas no PIX?',
                'a' => 'O prazo é de até 48 horas úteis para que o reembolso seja feito, após autorizado por um moderador ou imediatamente quando ele é emitido diretamente pelo vendedor.',
            ],
            [
                'q' => 'Qual o prazo para o reembolso para compras realizadas no Cartão de Crédito?',
                'a' => 'O estorno é repassado para a operadora do cartão, caso a fatura já tenha sido fechada, o comprador recebe como crédito na fatura do mês seguinte, do contrário, o abatimento do valor ocorre no período em que o reembolso foi pedido.',
            ],
        ],
    ],
    'retiradas' => [
        'label' => 'Retiradas',
        'icon'  => 'banknote',
        'items' => [
            [
                'q' => 'Quais métodos em que posso solicitar uma retirada?',
                'a' => 'Você poderá escolher se deseja receber sua retirada para sua chave PIX.',
            ],
            [
                'q' => 'Qual prazo para ser efetuada as solicitações de retiradas?',
                'a' => 'Temos 2 formas de saque: a <strong>Retirada Normal</strong> é Grátis e tem prazo de até 2 dias úteis. A <strong>Retirada TURBO</strong> custa R$ 3,50 e é feito de forma Imediata.',
            ],
            [
                'q' => 'Para retirar é preciso ter a conta 100% verificada?',
                'a' => 'Sim. Infelizmente a verificação de documentos é um mal necessário, pois estamos lidando com dinheiro. Entendemos que possa ser burocrático fazer a verificação, porém isso garante segurança ao comprador, pois o vendedor caso aja de má fé será responsabilizado. Só é preciso realizar a confirmação uma única vez.',
            ],
        ],
    ],
    'outros' => [
        'label' => 'Outros',
        'icon'  => 'more-horizontal',
        'items' => [
            [
                'q' => 'Fui banido da Plataforma, e agora?',
                'a' => 'Se você foi banido provavelmente você quebrou alguma regra e diretriz em nossa Plataforma. Como nós prezamos pela credibilidade e qualidade de usuários em nossa plataforma, não há nenhuma garantia que você será desbanido. Você deverá preencher nosso Formulário de Banimento que aparece na tela do seu banimento.',
            ],
            [
                'q' => 'Tenho uma proposta para parcerias, como posso entrar em contato?',
                'a' => 'Deeemais! Caso queira entrar em contato referente a parcerias, patrocínios ou semelhante pedimos que encaminhe um email para <a href="mailto:contato@mercadoadmin.com.br" class="text-greenx hover:underline">contato@mercadoadmin.com.br</a> com sua proposta. Neste email nós NÃO realizamos nenhum tipo de suporte, caso esse seja seu caso utilize nossa <a href="/central_ajuda" class="text-greenx hover:underline">Central de Ajuda</a>.',
            ],
        ],
    ],
];
?>

<style>
    .faq-sidebar-btn { transition: all 0.2s ease; }
    .faq-sidebar-btn.faq-active {
        background: rgba(var(--t-accent-rgb), 0.12);
        border-left: 3px solid var(--t-accent);
        color: var(--t-accent);
        font-weight: 600;
    }
    .faq-answer { overflow: hidden; transition: max-height 0.35s ease, opacity 0.25s ease; max-height: 0; opacity: 0; }
    .faq-answer.faq-open { max-height: 800px; opacity: 1; }
    .faq-chevron { transition: transform 0.3s ease; }
    .faq-chevron.faq-rotated { transform: rotate(180deg); }
</style>

<div class="min-h-screen bg-blackx">
    <!-- Breadcrumb -->
    <div class="max-w-6xl mx-auto px-4 sm:px-6 pt-6">
        <nav class="flex items-center gap-2 text-sm text-zinc-500 animate-fade-in">
            <a href="/" class="hover:text-greenx transition-colors">Início</a>
            <i data-lucide="chevron-right" class="w-3.5 h-3.5"></i>
            <a href="/central_ajuda" class="hover:text-greenx transition-colors">Central de Ajuda</a>
            <i data-lucide="chevron-right" class="w-3.5 h-3.5"></i>
            <span class="text-zinc-300">FAQ</span>
        </nav>
    </div>

    <!-- Header -->
    <section class="max-w-6xl mx-auto px-4 sm:px-6 pt-8 pb-4 text-center animate-fade-in-up">
        <h1 class="text-3xl md:text-4xl font-black mb-3">Perguntas frequentes (FAQ)</h1>
        <p class="text-zinc-400 text-lg">Tire suas dúvidas</p>
    </section>

    <!-- Layout: Sidebar + Content -->
    <section class="max-w-6xl mx-auto px-4 sm:px-6 pb-16 pt-6">
        <div class="flex flex-col md:flex-row gap-6">

            <!-- Sidebar -->
            <aside class="w-full md:w-56 shrink-0">
                <div class="md:sticky md:top-24 bg-blackx2 border border-white/[0.06] rounded-2xl p-4">
                    <p class="text-[11px] uppercase tracking-wider text-zinc-500 font-semibold mb-3 px-2">Tópicos</p>
                    <nav id="faqTopicNav" class="space-y-1">
                        <?php $first = true; foreach ($topics as $key => $topic): ?>
                        <a href="#faq-<?= $key ?>"
                           class="faq-sidebar-btn w-full text-left flex items-center gap-2 rounded-lg px-3 py-2.5 text-sm text-zinc-300 hover:bg-white/[0.04] no-underline <?= $first ? 'faq-active' : '' ?>"
                           data-topic="<?= $key ?>">
                            <i data-lucide="<?= $topic['icon'] ?>" class="w-4 h-4 shrink-0"></i>
                            <span><?= htmlspecialchars($topic['label']) ?></span>
                        </a>
                        <?php $first = false; endforeach; ?>
                    </nav>
                </div>
            </aside>

            <!-- Content — all topics visible -->
            <div class="flex-1 min-w-0 space-y-12">
                <?php foreach ($topics as $key => $topic): ?>
                <div id="faq-<?= $key ?>" class="faq-topic-section scroll-mt-28">
                    <!-- Topic Title -->
                    <div class="flex items-center gap-3 mb-6">
                        <div class="w-10 h-10 rounded-xl bg-greenx/10 border border-greenx/20 flex items-center justify-center">
                            <i data-lucide="<?= $topic['icon'] ?>" class="w-5 h-5 text-greenx"></i>
                        </div>
                        <h2 class="text-2xl font-bold"><?= htmlspecialchars($topic['label']) ?></h2>
                    </div>

                    <!-- Questions -->
                    <div class="space-y-3">
                        <?php foreach ($topic['items'] as $i => $item): ?>
                        <div class="bg-blackx2 border border-white/[0.06] rounded-xl overflow-hidden hover:border-white/[0.10] transition-colors">
                            <button onclick="toggleFaq(this)"
                                    class="w-full text-left flex items-center justify-between gap-3 px-5 py-4 group">
                                <span class="text-sm font-semibold text-zinc-100 group-hover:text-greenx transition-colors"><?= htmlspecialchars($item['q']) ?></span>
                                <i data-lucide="chevron-down" class="w-4 h-4 text-zinc-500 flex-shrink-0 faq-chevron"></i>
                            </button>
                            <div class="faq-answer">
                                <div class="px-5 pb-5 border-l-3 border-greenx ml-5">
                                    <div class="border-l-[3px] border-greenx pl-4">
                                        <p class="text-sm text-zinc-400 leading-relaxed"><?= $item['a'] ?></p>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>

        </div>
    </section>
</div>

<script>
function toggleFaq(btn) {
    var answer = btn.nextElementSibling;
    var chevron = btn.querySelector('.faq-chevron');
    if (answer.classList.contains('faq-open')) {
        answer.classList.remove('faq-open');
        if (chevron) chevron.classList.remove('faq-rotated');
    } else {
        answer.classList.add('faq-open');
        if (chevron) chevron.classList.add('faq-rotated');
    }
}

// Scroll-spy: highlight sidebar item for the currently visible section
(function() {
    var sections = document.querySelectorAll('.faq-topic-section');
    var navBtns  = document.querySelectorAll('.faq-sidebar-btn');
    if (!sections.length || !navBtns.length) return;

    var observer = new IntersectionObserver(function(entries) {
        entries.forEach(function(entry) {
            if (entry.isIntersecting) {
                var key = entry.target.id.replace('faq-', '');
                navBtns.forEach(function(btn) {
                    btn.classList.toggle('faq-active', btn.getAttribute('data-topic') === key);
                });
            }
        });
    }, {
        rootMargin: '-20% 0px -60% 0px',
        threshold: 0
    });

    sections.forEach(function(s) { observer.observe(s); });

    // Smooth scroll on sidebar click
    navBtns.forEach(function(btn) {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            var target = document.getElementById('faq-' + btn.getAttribute('data-topic'));
            if (target) target.scrollIntoView({ behavior: 'smooth', block: 'start' });
        });
    });
})();
</script>

<?php
include __DIR__ . '/../views/partials/storefront_footer.php';
include __DIR__ . '/../views/partials/footer.php';
?>
