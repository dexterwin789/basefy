<?php
declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) session_start();

require_once __DIR__ . '/../src/db.php';
require_once __DIR__ . '/../src/storefront.php';

$conn      = (new Database())->connect();
$cartCount = sfCartCount();

$currentPage = 'privacidade';
$pageTitle   = 'Política de Privacidade — Basefy';

include __DIR__ . '/../views/partials/header.php';
include __DIR__ . '/../views/partials/storefront_nav.php';
?>

<style>
    .legal-fade { opacity: 0; transform: translateY(20px); animation: legalFade 0.6s ease forwards; }
    @keyframes legalFade { to { opacity: 1; transform: translateY(0); } }
    .legal-delay-1 { animation-delay: 0.05s; }
    .legal-delay-2 { animation-delay: 0.10s; }

    .legal-sidebar::-webkit-scrollbar { width: 3px; }
    .legal-sidebar::-webkit-scrollbar-thumb { background: rgba(var(--t-accent-rgb),0.3); border-radius: 99px; }

    .legal-section { scroll-margin-top: 100px; }

    .legal-nav-link {
        display: flex; align-items: center; gap: 10px; padding: 8px 14px;
        border-radius: 10px; font-size: 13px; color: #a1a1aa; transition: all 0.2s;
        border-left: 2px solid transparent; cursor: pointer;
    }
    .legal-nav-link:hover { color: #fff; background: rgba(255,255,255,0.04); }
    .legal-nav-link.active { color: var(--t-accent); border-left-color: var(--t-accent); background: rgba(var(--t-accent-rgb),0.06); font-weight: 600; }

    .legal-body h3 { font-size: 1.1rem; font-weight: 700; margin: 2rem 0 0.8rem; padding-bottom: 0.5rem; border-bottom: 1px solid rgba(255,255,255,0.06); }
    .legal-body h3:first-child { margin-top: 0; }
    .legal-body p { color: #a1a1aa; font-size: 0.9rem; line-height: 1.8; margin-bottom: 0.8rem; }
    .legal-body ul { list-style: none; padding: 0; margin: 0.5rem 0 1rem; }
    .legal-body ul li { position: relative; padding-left: 1.5rem; color: #a1a1aa; font-size: 0.9rem; line-height: 1.8; margin-bottom: 0.35rem; }
    .legal-body ul li::before { content: ''; position: absolute; left: 0; top: 0.65rem; width: 6px; height: 6px; border-radius: 50%; background: var(--t-accent); opacity: 0.6; }

    .light-mode .legal-body h3 { border-bottom-color: rgba(0,0,0,0.08); }
    .light-mode .legal-body p,
    .light-mode .legal-body ul li { color: #3f3f46; }
    .light-mode .legal-nav-link { color: #52525b; }
    .light-mode .legal-nav-link:hover { color: #18181b; background: rgba(0,0,0,0.04); }
    .light-mode .legal-nav-link.active { color: var(--t-accent); background: rgba(var(--t-accent-rgb),0.08); }
</style>

<div class="min-h-screen bg-blackx" x-data="legalNav()">

    <!-- Breadcrumb -->
    <div class="max-w-6xl mx-auto px-4 sm:px-6 pt-6 legal-fade">
        <nav class="flex items-center gap-2 text-sm text-zinc-500">
            <a href="/" class="hover:text-greenx transition-colors">Início</a>
            <i data-lucide="chevron-right" class="w-3.5 h-3.5"></i>
            <a href="<?= BASE_PATH ?>/central_ajuda" class="hover:text-greenx transition-colors">Central de Ajuda</a>
            <i data-lucide="chevron-right" class="w-3.5 h-3.5"></i>
            <span class="text-zinc-300">Política de Privacidade</span>
        </nav>
    </div>

    <!-- Hero -->
    <section class="max-w-6xl mx-auto px-4 sm:px-6 pt-8 pb-8 text-center legal-fade legal-delay-1">
        <div class="inline-flex items-center gap-2 px-4 py-1.5 rounded-full bg-greenx/10 border border-greenx/20 text-greenx text-xs font-semibold mb-5">
            <i data-lucide="shield" class="w-3.5 h-3.5"></i> Privacidade e Segurança
        </div>
        <h1 class="text-3xl md:text-4xl font-black tracking-tight">Política de Privacidade</h1>
        <p class="text-zinc-400 mt-3 text-sm max-w-2xl mx-auto">Saiba como coletamos, utilizamos e protegemos os seus dados pessoais na plataforma Basefy.</p>
        <p class="text-zinc-600 text-xs mt-2">Última atualização: Março de 2026</p>
    </section>

    <!-- Content -->
    <section class="max-w-6xl mx-auto px-4 sm:px-6 pb-20 legal-fade legal-delay-2">
        <div class="flex gap-8">

            <!-- Sidebar Nav (desktop) -->
            <aside class="hidden lg:block w-64 flex-shrink-0">
                <div class="sticky top-24 bg-blackx2 border border-white/[0.06] rounded-2xl p-4 legal-sidebar max-h-[calc(100vh-120px)] overflow-y-auto">
                    <p class="text-[11px] font-bold uppercase tracking-widest text-zinc-600 mb-3 px-3">Seções</p>
                    <nav class="space-y-1">
                        <a @click.prevent="scrollTo('p1')" :class="active==='p1'?'active':''" class="legal-nav-link">Compromisso</a>
                        <a @click.prevent="scrollTo('p2')" :class="active==='p2'?'active':''" class="legal-nav-link">Dados Coletados</a>
                        <a @click.prevent="scrollTo('p3')" :class="active==='p3'?'active':''" class="legal-nav-link">Dados Automáticos</a>
                        <a @click.prevent="scrollTo('p4')" :class="active==='p4'?'active':''" class="legal-nav-link">Dados de Terceiros</a>
                        <a @click.prevent="scrollTo('p5')" :class="active==='p5'?'active':''" class="legal-nav-link">Finalidades</a>
                        <a @click.prevent="scrollTo('p6')" :class="active==='p6'?'active':''" class="legal-nav-link">Compartilhamento</a>
                        <a @click.prevent="scrollTo('p7')" :class="active==='p7'?'active':''" class="legal-nav-link">Armazenamento</a>
                        <a @click.prevent="scrollTo('p8')" :class="active==='p8'?'active':''" class="legal-nav-link">Confidencialidade</a>
                        <a @click.prevent="scrollTo('p9')" :class="active==='p9'?'active':''" class="legal-nav-link">Gerenciamento</a>
                        <a @click.prevent="scrollTo('p10')" :class="active==='p10'?'active':''" class="legal-nav-link">Direitos LGPD</a>
                        <a @click.prevent="scrollTo('p11')" :class="active==='p11'?'active':''" class="legal-nav-link">Atualização</a>
                    </nav>
                </div>
            </aside>

            <!-- Main Content -->
            <div class="flex-1 min-w-0">
                <div class="bg-blackx2 border border-white/[0.06] rounded-2xl p-6 md:p-10 legal-body">

                    <!-- Intro -->
                    <div id="p1" class="legal-section">
                        <h3>Compromisso com a Privacidade</h3>
                        <p>A Basefy tem o compromisso com a privacidade e a segurança de seus clientes durante todo o processo de navegação e compra pelo site. A Basefy utiliza cookies e informações de sua navegação com o objetivo de traçar um perfil do público que visita o site e aperfeiçoar sempre nossos serviços, produtos, conteúdos e garantir as melhores ofertas e promoções para você. Durante todo este processo mantemos suas informações em sigilo.</p>
                    </div>

                    <!-- Dados voluntários -->
                    <div id="p2" class="legal-section">
                        <h3>Quais Dados Pessoais Coletamos</h3>
                        <p><strong>Dados Pessoais que o usuário nos fornece voluntariamente:</strong></p>
                        <ul>
                            <li>Nome completo</li>
                            <li>CPF</li>
                            <li>Data de nascimento</li>
                            <li>Celular</li>
                            <li>Endereço</li>
                            <li>E-mail</li>
                            <li>Senha</li>
                            <li>Dados de pagamento e de crédito</li>
                            <li>Preferências pessoais</li>
                        </ul>
                    </div>

                    <!-- Dados automáticos -->
                    <div id="p3" class="legal-section">
                        <h3>Dados Pessoais Coletados Automaticamente</h3>
                        <ul>
                            <li>Endereço IP</li>
                            <li>Geolocalização</li>
                            <li>Informações sobre o dispositivo de acesso (modelo, fabricante, sistema operacional)</li>
                            <li>Informações sobre o navegador de internet</li>
                            <li>Duração da visita</li>
                            <li>Páginas visitadas</li>
                            <li>Conteúdos interagidos</li>
                            <li>Cookies</li>
                            <li>Histórico de compras</li>
                            <li>Produtos pesquisados, selecionados ou adicionados ao carrinho</li>
                        </ul>
                    </div>

                    <!-- Dados de terceiros -->
                    <div id="p4" class="legal-section">
                        <h3>Dados Pessoais que Recebemos de Terceiros</h3>
                        <p>Nós também podemos receber, de serviços de terceiros, os Dados Pessoais que o Usuário decidir compartilhar conosco:</p>
                        <ul>
                            <li>Facebook</li>
                            <li>Discord</li>
                            <li>Google</li>
                        </ul>
                    </div>

                    <!-- Finalidades -->
                    <div id="p5" class="legal-section">
                        <h3>Para que Coletamos os Dados Pessoais</h3>
                        <p>Nós utilizamos os Dados Pessoais do Usuário para diversas finalidades em nossos serviços:</p>
                        <ul>
                            <li>Gerenciar e processar as compras, vendas, reembolsos.</li>
                            <li>Cumprir as obrigações decorrentes do uso dos nossos serviços.</li>
                            <li>Melhorar a experiência do Usuário e oferecer produtos e serviços alinhados ao seu perfil, além de melhorar a apresentação das Plataformas.</li>
                            <li>Enviar comunicação publicitária por e-mail, Discord, WhatsApp ou mensagens de texto.</li>
                            <li>Veicular publicidade na Internet de forma personalizada ou não.</li>
                            <li>Permitir a nossa comunicação com o Usuário, atender às suas solicitações, responder suas dúvidas e reclamações.</li>
                            <li>Implementar medidas adequadas de segurança para resguardar os Usuários.</li>
                            <li>Corrigir eventuais problemas técnicos encontrados em nossas Plataformas.</li>
                            <li>Proteger os Usuários e a Basefy contra fraudes, incluindo a investigação de ações fraudulentas envolvendo alterações cadastrais de compra e entrega.</li>
                            <li>Cumprir nossa obrigação legal de manter o registro de acesso dos Usuários à Plataforma.</li>
                        </ul>
                        <p>Muitos de nossos serviços dependem diretamente de alguns Dados Pessoais informados acima, principalmente Dados Pessoais relacionados a cadastro. Caso você opte por não fornecer alguns Dados Pessoais de cadastro, podemos ficar impossibilitados de prestar nossos serviços com totalidade.</p>
                        <p>Nós utilizamos, em nossas Plataformas, as seguintes tecnologias:</p>
                        <ul>
                            <li>Cookies</li>
                            <li>Google Ads, registros de navegação, Facebook (registro de eventos) e Google Analytics (dados de navegação)</li>
                        </ul>
                    </div>

                    <!-- Compartilhamento -->
                    <div id="p6" class="legal-section">
                        <h3>Como Compartilhamos seus Dados Pessoais</h3>
                        <p>Nós podemos compartilhar os Dados Pessoais com terceiros, preservando ao máximo a privacidade do Usuário e, sempre que possível, de forma anonimizada.</p>
                        <p>Abaixo, descrevemos situações em que podemos compartilhar Dados Pessoais:</p>
                        <ul>
                            <li>Internamente, com nossas áreas de negócio, para realizar segmentação de perfil (proporcionar experiências personalizadas), enviar publicidade, promover e desenvolver nossos serviços.</li>
                            <li>Com nossos fornecedores e parceiros comerciais, com quem firmamos obrigações contratuais de segurança e proteção de dados pessoais. Os fornecedores incluem empresas de hospedagem de dados e servidores; empresas de segurança; empresas de autenticação e validação de cadastros; ferramentas de publicidade, marketing, mídia digital e social; empresas de logística e entrega dos produtos; empresas de pesquisa; empresas de meios e processamento de pagamento.</li>
                            <li>Com Autoridades Públicas, sempre que houver determinação legal, requerimento, requisição ou ordem judicial nesse sentido.</li>
                            <li>De forma automática, em caso de movimentações societárias, como fusão, aquisição e incorporação das empresas do Grupo Basefy.</li>
                            <li>Constatada ação de má fé ou fraude, sob solicitação da parte lesada com justificativa e objetivo exclusivos para fins judiciais.</li>
                        </ul>
                        <p>Para pesquisas de inteligência de mercado, divulgação de dados à imprensa e realização de propagandas, os Dados Pessoais serão compartilhados de forma anonimizada.</p>
                        <p>Os Dados Pessoais podem ser transferidos para fora do Brasil. Essa transferência ocorre porque alguns dos nossos fornecedores estão localizados no exterior que estão em conformidade com as leis aplicáveis de proteção de Dados Pessoais.</p>
                    </div>

                    <!-- Armazenamento -->
                    <div id="p7" class="legal-section">
                        <h3>Como Armazenamos seus Dados Pessoais</h3>
                        <p>Os Dados Pessoais são armazenados somente pelo tempo que for necessário para cumprir com as finalidades definidas, salvo se houver outra razão para sua manutenção como o cumprimento de obrigações legais, regulatórias, contratuais e preservação de direitos da Basefy, desde que devidamente fundamentadas por legislação vigente.</p>
                        <p>Os requerimentos de exclusão total dos Dados pessoais serão avaliados para que se cumpra com os requisitos normativos, de investigação e cumprimentos legais, previsto no art. 16 da LGPD. Terminado o prazo de manutenção e necessidade legal, os Dados Pessoais serão excluídos com uso de métodos de descarte seguro, ou utilizados de forma anonimizada para fins estatísticos.</p>
                        <p>Uma vez cadastrada, a conta do usuário não poderá ser excluída de nosso banco de dados em caso de atividades (compras e vendas), isso garante a segurança de nossa Plataforma. A Basefy não é obrigada a excluir os dados de usuários caso exista alguma base legal que justifique a manutenção deles. O usuário poderá solicitar a desativação da conta entrando em "Meus Dados". Caso não haja nenhuma atividade na conta do usuário, o mesmo poderá solicitar a exclusão clicando no botão "Excluir Conta".</p>
                    </div>

                    <!-- Confidencialidade -->
                    <div id="p8" class="legal-section">
                        <h3>Confidencialidade e Segurança</h3>
                        <p>O Usuário também é responsável pelo sigilo dos seus Dados Pessoais e deve ter sempre ciência de que o compartilhamento de senhas viola esta Política e pode comprometer a segurança dos seus Dados Pessoais.</p>
                        <p>É muito importante que o Usuário se proteja contra acesso não autorizado ao seu computador ou conta, além de se certificar de sempre clicar em "sair" ao encerrar sua navegação em um computador compartilhado.</p>
                        <p>Internamente, os Dados Pessoais coletados são acessados somente por profissionais autorizados, respeitando os princípios de proporcionalidade, necessidade e relevância, além do compromisso de confidencialidade e preservação da privacidade.</p>
                        <p>As transações de pagamento são executadas sob protocolos de segurança e criptografia garantindo que os Dados Pessoais, inclusive dados de cartão de crédito, não sejam ilicitamente divulgados a terceiros.</p>
                        <p>Ao utilizar nossas Plataformas, o Usuário poderá ser conduzido, via link, a sites de terceiros, que poderão coletar suas informações e ter suas próprias Políticas de Privacidade. Nós recomendamos que o Usuário leia as Políticas de Privacidade de tais sites, sendo de sua responsabilidade aceitá-las ou rejeitá-las. A Basefy não se responsabiliza pelas Políticas de Privacidade, conteúdos ou serviços dos sites de terceiros.</p>
                    </div>

                    <!-- Gerenciamento -->
                    <div id="p9" class="legal-section">
                        <h3>Gerenciamento de Dados e Informações</h3>
                        <p>Em caso de violação dos Termos de Uso ou práticas consideradas fraudulentas ou prejudiciais à plataforma ou a terceiros, a Basefy reserva-se o direito de manter e armazenar os dados do usuário, incluindo, mas não se limitando a: nome completo, CPF, e-mail, número de telefone, IPs de acesso, conversas, transações e qualquer outra informação necessária para fins legais, administrativos e de segurança, mesmo após a exclusão ou suspensão da conta.</p>
                    </div>

                    <!-- LGPD -->
                    <div id="p10" class="legal-section">
                        <h3>Direitos do Titular — LGPD</h3>
                        <p>A LGPD garante ao Usuário, como Titular dos Dados Pessoais, os seguintes direitos:</p>
                        <ul>
                            <li><strong>Acesso:</strong> O usuário pode requisitar uma cópia dos Dados Pessoais que nós temos sobre ele.</li>
                            <li><strong>Anonimização, bloqueio ou eliminação:</strong> Possibilidade de solicitar a anonimização dos Dados Pessoais; o bloqueio, suspendendo temporariamente o Tratamento, e eliminação, caso em que apagaremos todos os Dados Pessoais, salvo os casos em que a lei exigir sua manutenção.</li>
                            <li><strong>Correção:</strong> O Usuário pode corrigir, na área "Meus Dados", os Dados Pessoais que estejam incompletos ou desatualizados.</li>
                            <li><strong>Revogação do consentimento:</strong> O Usuário tem o direito de não dar ou retirar seu consentimento e obter informações sobre as consequências dessa escolha. Nesse caso, alguns dos serviços não poderão ser prestados.</li>
                        </ul>
                    </div>

                    <!-- Atualização -->
                    <div id="p11" class="legal-section">
                        <h3>Atualização e Contato</h3>
                        <p>Trabalhamos em melhorias constantes para aperfeiçoamento de nossos serviços, produtos, conteúdo e navegação, garantindo uma melhor experiência.</p>
                        <p>O Usuário reconhece o direito da Basefy de alterar o teor desta Política a qualquer momento, conforme a finalidade ou necessidade, sem aviso prévio. Caso algum ponto desta Política seja considerado inaplicável, as demais condições permanecerão em pleno vigor e efeito.</p>
                        <p>Esta Política será interpretada segundo a legislação brasileira, no idioma português, sendo eleito o foro de Maringá/PR para dirimir qualquer controvérsia que envolva este documento, salvo ressalva específica de competência pessoal, territorial ou funcional pela legislação aplicável. Caso o Usuário não possua domicílio no Brasil, e em razão dos serviços oferecidos pela Basefy apenas em território nacional, será aplicada a legislação brasileira, concordando que, em caso de litígio, a ação deverá ser proposta no Foro da Comarca de Maringá/PR.</p>
                    </div>

                </div>
            </div>
        </div>
    </section>
</div>

<script>
function legalNav() {
    return {
        active: 'p1',
        sections: [],
        init() {
            this.sections = [...document.querySelectorAll('.legal-section')];
            const observer = new IntersectionObserver((entries) => {
                entries.forEach(e => {
                    if (e.isIntersecting) this.active = e.target.id;
                });
            }, { rootMargin: '-100px 0px -60% 0px', threshold: 0 });
            this.sections.forEach(s => observer.observe(s));
        },
        scrollTo(id) {
            document.getElementById(id)?.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
    };
}
</script>

<?php
include __DIR__ . '/../views/partials/storefront_footer.php';
include __DIR__ . '/../views/partials/footer.php';
?>
